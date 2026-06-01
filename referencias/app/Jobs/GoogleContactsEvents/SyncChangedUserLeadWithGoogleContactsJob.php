<?php

namespace App\Jobs\GoogleContactsEvents;

use Exception;
use Throwable;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\GoogleAPIUserContactService;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Jobs\GoogleContactsEvents\Traits\InjectLog;


class SyncChangedUserLeadWithGoogleContactsJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = 120;
    
    public $leadId;
    public $oldUserId;
    public $jobUniqId;
   

    public function __construct(int $leadId, int $oldUserId)
    {
        $this->leadId = $leadId;
        $this->oldUserId = $oldUserId;

        $this->jobUniqId = uniqid() . '-' . $leadId;
    }


    public function handle()
    {
        $this->logInfo('-------- STARTING SyncChangedUserLeadWithGoogleContactsJob --------');
        $this->logInfo("- leadId: {$this->leadId}");
        $this->logInfo("- oldUserId: {$this->oldUserId}");

        $lead = Lead::findOrFail($this->leadId);
        $clientSettings = $lead->client->clientSettings;
        $this->logInfo("- clientId: {$lead->client->id}");

        if (!$clientSettings->enable_google_contacts_api) {
            $this->logInfo('-------- ENDING JOB - clientSettings.enable_google_contacts_api IS FALSE --------');
            return null;
        }
        if ($clientSettings->google_contacts_api_sync_scope == 'client') {
            $this->logInfo('-------- ENDING JOB - clientSettings.google_contacts_api_sync_scope IS "client" --------');
            return null;
        }
        if (environmentIsNotProduction()) {
            $this->logInfo('-------- ENDING JOB - Environment IS NOT PROD --------');
            return null;
        }

        // $oldUser = User::findOrFail($this->oldUserId);
        // $oldUserExistentContact = $lead->getGoogleAPIUserContact($oldUser);
        // if ($oldUserExistentContact) {
        //     $this->unsync($lead, $oldUser);
        // }
        
        $newUserExistentContact = $lead->getGoogleAPIUserContact($lead->user);
        if ($newUserExistentContact) {
            $this->logInfo('-------- ENDING JOB - getGoogleAPIUserContact ALREADY EXISTS --------');
            return null;
        }

        try {
            $this->sync($lead, $lead->user);
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


    // protected function unsync(Lead $lead, User $user)
    // {
    //     if (!$user->googlePeopleAPIUserToken) {
    //         return null;
    //     }
    //     $userContactService = resolve(GoogleAPIUserContactService::class);
    //     $userContactService->unsyncLead($lead, $user);
    // }


    protected function sync(Lead $lead, User $user)
    {
        $this->logInfo("- userId: {$user->id}");
        $this->logInfo('--- SYNCHRONIZING');

        if (!$user->googlePeopleAPIUserToken) {
            $this->logInfo('-------- ENDING JOB - user.googlePeopleAPIUserToken DOES NOT EXISTS --------');
            return null;
        }
        $userContactService = resolve(GoogleAPIUserContactService::class);
        $userContactService->syncLead($lead, $user);

        $this->logInfo('--- SYNCHRONIZED');
    }


    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->jobUniqId}] | {$msg}");
    }


    protected function logFailAtErrorLog(Throwable $e)
    {
        $jobUniqId = $this->jobUniqId;
        $errorLog = $this->getErrorLog();
        $errorLog->error("[{$jobUniqId}] | -------- ERROR SyncChangedUserLeadWithGoogleContactsJob --------");
        $errorLog->error("[{$jobUniqId}] | - Attempt: {$this->attempts()}");
        $errorLog->error("[{$jobUniqId}] | {$e}");
        $errorLog->error("[{$jobUniqId}] | -------- /ERROR SyncChangedUserLeadWithGoogleContactsJob --------");
    }


    protected function logFailAtInfoLog(Throwable $e)
    {
        $jobUniqId = $this->jobUniqId;
        $this->logInfo("-------- ERROR SyncChangedUserLeadWithGoogleContactsJob --------");
        $this->logInfo("- Attempt: {$this->attempts()}");
        $this->logInfo("{$e}");
        $this->logInfo("-------- /ERROR SyncChangedUserLeadWithGoogleContactsJob --------");
    }


    public function failed(Throwable $e)
    {
        $this->logFailAtInfoLog($e);
        $this->logFailAtErrorLog($e);
    }

}
