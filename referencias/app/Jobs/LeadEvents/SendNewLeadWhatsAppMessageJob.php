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
use App\Services\API\LeadNotificationWhatsAppMessageService;


class SendNewLeadWhatsAppMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 30;
    
    public int $leadId;
    

    public function __construct(int $leadId)
    {
        $this->leadId = $leadId;
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $service = resolve(LeadNotificationWhatsAppMessageService::class);
        $service->sendNewLeadWhatsAppMessageNotificationToLeadUser($lead);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
