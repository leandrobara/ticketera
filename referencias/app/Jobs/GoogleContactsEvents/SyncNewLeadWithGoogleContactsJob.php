<?php

namespace App\Jobs\GoogleContactsEvents;

use Exception;
use Throwable;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Helpers\QueuedJobsCounter;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\GoogleAPIUserContactService;
use App\Jobs\GoogleContactsEvents\Traits\InjectLog;


class SyncNewLeadWithGoogleContactsJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = 120;
    
    public $leadId;
    public $userId;
    public $lockKey;
    public $jobUniqId;
    
    
    public function __construct(int $leadId, ?int $userId = null)
    {
        $this->leadId = $leadId;
        $this->userId = $userId;
        $this->jobUniqId = uniqid() . '-' . $leadId;
    }


    public function handle()
    {
        $this->logInfo('-------- STARTING SyncNewLeadWithGoogleContactsJob --------');
        $this->logInfo("- leadId: {$this->leadId}");

        $lead = Lead::findOrFail($this->leadId);
        $clientSettings = $lead->client->clientSettings;
        $this->logInfo("- clientId: {$lead->client->id}");
        
        if (!$clientSettings->enable_google_contacts_api) {
            $this->logInfo('-------- ENDING JOB - clientSettings.enable_google_contacts_api IS FALSE --------');
            return null;
        }
        if (environmentIsNotProduction()) {
            $this->logInfo('-------- ENDING JOB - Environment IS NOT PROD --------');
            return null;
        }

        try {
            $syncScope = $clientSettings->google_contacts_api_sync_scope;
            if ($syncScope == 'user') {
                $user = $lead->user;
                if ($this->userId) {
                    $user = User::findOrFail($this->userId);
                }
                $this->sync($lead, $user);
            }
            if ($syncScope == 'client') {
                foreach ($lead->client->users as $user) {
                    $this->sync($lead, $user);
                }
            }
        } catch (Exception $e) {
            // Si NO es el último intento, el método failed() no va a ser llamado, por eso registro manualmente.
            if ($this->attempts() < $this->tries) {
                $this->logFailAtErrorLog($e);
                $this->logFailAtInfoLog($e);
            }
            throw $e;
        }

        $this->logInfo('-------- SUCCESFULLY ENDED JOB --------');
    }


    protected function sync(Lead $lead, User $user)
    {
        $this->logInfo("- userId: {$user->id}");
        $this->logInfo("--- SYNCHRONIZING userId {$user->id}");

        if (!$user->googlePeopleAPIUserToken) {
            $this->logInfo('-------- ENDING JOB - user.googlePeopleAPIUserToken DOES NOT EXISTS --------');
            return null;
        }
        $userContactService = resolve(GoogleAPIUserContactService::class);
        $userContactService->syncLead($lead, $user);

        $this->logInfo("--- SYNCHRONIZED userId {$user->id}");
    }


    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->jobUniqId}] | {$msg}");
    }


    protected function logFailAtErrorLog(Throwable $e)
    {
        $jobUniqId = $this->jobUniqId;
        $this->getErrorLog()->error("[{$jobUniqId}] | -------- ERROR SyncNewLeadWithGoogleContactsJob --------");
        $this->getErrorLog()->error("[{$jobUniqId}] | - Attempt: {$this->attempts()}");
        $this->getErrorLog()->error("[{$jobUniqId}] | {$e}");
        $this->getErrorLog()->error("[{$jobUniqId}] | -------- /ERROR SyncNewLeadWithGoogleContactsJob --------");
    }


    protected function logFailAtInfoLog(Throwable $e)
    {
        $jobUniqId = $this->jobUniqId;
        $this->logInfo("-------- ERROR SyncNewLeadWithGoogleContactsJob --------");
        $this->logInfo("- Attempt: {$this->attempts()}");
        $this->logInfo("{$e}");
        $this->logInfo("-------- /ERROR SyncNewLeadWithGoogleContactsJob --------");
    }


    public function failed(Throwable $e)
    {
        $this->logFailAtInfoLog($e);
        $this->logFailAtErrorLog($e);
    }

}
