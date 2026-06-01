<?php

namespace App\Jobs\EmailEvents;

use Throwable;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use App\Models\LeadContactEmail;
use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\Actions\LeadService;
use App\Jobs\EmailEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\LeadContactEmailService;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;


class MarkLeadContactEmailsAsComplainedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $clientId;
    public $leadContactEmailIds;


    public function __construct(int $clientId, array $leadContactEmailIds)
    {
        $this->clientId = $clientId;
        $this->leadContactEmailIds = $leadContactEmailIds;
    }


    public function handle()
    {
        $lockKey = 'MarkLeadContactEmailsAsComplainedJob:handle:' . implode(',', $this->leadContactEmailIds);
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, 3);
        if (!$lockIsGranted) {
            return null;
        }
        
        $client = Client::findOrFail($this->clientId);
        
        $service = resolve(LeadContactEmailService::class);
        $leadContactEmailIds = collect($this->leadContactEmailIds);
        $leadContactEmails = $service->findByClientAndIds($client, $leadContactEmailIds);

        $service->markAsComplained($leadContactEmails);

        if ($client->clientSettings->see_disabled_email_reason_as_label) {
            $this->assignUnsubscribedEmailTagToLead($leadContactEmails);
        }
    }


    public function assignUnsubscribedEmailTagToLead(Collection $leadContactEmails)
    {
        $leadService = resolve(LeadService::class);
        foreach ($leadContactEmails as $leadContactEmail) {
            $lead = $leadContactEmail->lead()->withTrashed()->first();
            $leadService->assignUnsubscribedEmailTag($lead);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
