<?php

namespace App\Jobs\AutomationEvents;

use DateTime;
use Throwable;
use Exception;
use Illuminate\Bus\Queueable;
use App\Models\AutomationTask;
use App\Services\API\UserService;
use App\Models\AutomationNewLead;
use App\Models\AutomationEmailSend;
use App\Models\WAutomationSequence;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;


class SendDeletedAutomationEmailAlertJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $backoff = 60;
    
    public $loginUserId;
    public $deletedAutomation;
    

    public function __construct(
        AutomationTask |
        AutomationNewLead |
        AutomationEmailSend |
        WAutomationSequence $deletedAutomation,
        int $loginUserId
    ) {
        $this->loginUserId = $loginUserId;
        $this->deletedAutomation = $deletedAutomation;
    }


    public function handle()
    {
        $loginUser = resolve(UserService::class)->findOrFail($this->loginUserId);

        $clientName = $loginUser->client->name;
        $subject = "Clienty CRM | {$clientName} - Automatización borrada";
        $automationClassName = basename(str_replace('\\', '/', get_class($this->deletedAutomation)));
        
        $fromAddress = config('emails.leads_notification_from_email');
        $toAddresses = config('emails.deleted_automation_alert_emails_to');

        $viewData = [
            'loginUser' => $loginUser,
            'automationClassName' => $automationClassName,
            'deletedAutomation' => $this->deletedAutomation,
        ];
        $body = view('api.emails.automation-deleted', $viewData)->render();
        $data = [
            'body' => $body,
            'to' => $toAddresses,
            'subject' => $subject,
            'from' => $fromAddress,
            'fromName' => 'Clienty CRM',
            'sendDate' => (new DateTime())->format('Y-m-d\TH:i:sP'),
            'appCustomId' => 'SYSTEM_deleted_automation_by_client',
            'appCustomMetadata' => json_encode([
                'login_user_id' => $loginUser->id, 'deleted_automation_id' => $this->deletedAutomation->id
            ]),
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }

        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = resolve(ClientyMailerAPIHelper::class)->scheduleQuickEmail($dto);
    }

}
