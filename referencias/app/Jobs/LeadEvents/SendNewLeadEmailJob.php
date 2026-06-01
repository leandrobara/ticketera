<?php

namespace App\Jobs\LeadEvents;

use Throwable;
use Exception;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use App\Models\LeadNotificationEmail;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\LeadNotificationEmailService;


class SendNewLeadEmailJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 30;
    
    public $leadId;
    

    public function __construct(int $leadId)
    {
        $this->leadId = $leadId;
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $service = resolve(LeadNotificationEmailService::class);
        $leadNotificationEmail = $service->sendNewLeadNotificationEmailToLeadUser($lead);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
