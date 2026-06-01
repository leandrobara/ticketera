<?php

namespace App\Jobs\GoogleContactsEvents;

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


class SyncUpdatedLeadWithGoogleContactsJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = 120;
    
    public $leadId;
    

    public function __construct(int $leadId)
    {
        $this->leadId = $leadId;
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $clientSettings = $lead->client->clientSettings;
        
        $syncScope = $clientSettings->google_contacts_api_sync_scope;
        if (!$clientSettings->enable_google_contacts_api) {
            return null;
        }
        if (environmentIsNotProduction()) {
            return null;
        }

        $googleAPIUserContacts = $lead->googleAPIUserContacts;
        foreach ($googleAPIUserContacts as $googleAPIUserContact) {
            $this->sync($lead, $googleAPIUserContact->user);
        }
    }


    protected function sync(Lead $lead, User $user)
    {
        if (!$user->googlePeopleAPIUserToken) {
            return null;
        }
        $userContactService = resolve(GoogleAPIUserContactService::class);
        $userContactService->syncLead($lead, $user);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
