<?php

namespace App\Jobs\LeadEvents;

use Throwable;
use Exception;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Models\LeadNotificationEmail;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\LeadNotificationEmailService;


class SendLeadUserChangeEmailJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 30;
    
    public $leadId;
    public $oldUserId;
    

    public function __construct(int $leadId, int $oldUserId)
    {
        $this->leadId = $leadId;
        $this->oldUserId = $oldUserId;
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $oldUser = resolve(UserService::class)->find($this->oldUserId);

        $service = resolve(LeadNotificationEmailService::class);
        $leadNotificationEmail = $service->sendLeadUserChangeNotificationEmailToLeadUser($lead, $oldUser);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
