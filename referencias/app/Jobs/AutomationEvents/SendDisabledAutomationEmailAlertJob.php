<?php

namespace App\Jobs\AutomationEvents;

use DateTime;
use Throwable;
use Exception;
use Illuminate\Bus\Queueable;
use App\Models\AutomationTask;
use App\Services\API\UserService;
use App\Models\AutomationProposal;
use App\Models\WAutomationProposal;
use App\Models\AutomationEmailSend;
use App\Models\WAutomationSequence;
use App\Models\WAutomationAfterSend;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;


class SendDisabledAutomationEmailAlertJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $backoff = 60;
    
    public $loginUserId;
    public $disabledAutomation;
    

    public function __construct(
        AutomationTask |
        AutomationProposal |
        AutomationEmailSend |
        WAutomationProposal |
        WAutomationSequence |
        WAutomationAfterSend $disabledAutomation,
        int $loginUserId
    ) {
        $this->loginUserId = $loginUserId;
        $this->disabledAutomation = $disabledAutomation;
    }


    public function handle()
    {
        $loginUser = resolve(UserService::class)->findOrFail($this->loginUserId);

        $clientName = $loginUser->client->name;
        $subject = "Clienty CRM | {$clientName} - Automatización deshabilitada";
        $automationClassName = basename(str_replace('\\', '/', get_class($this->disabledAutomation)));
        
        $fromAddress = config('emails.leads_notification_from_email');
        $toAddresses = config('emails.deleted_automation_alert_emails_to');

        $viewData = [
            'loginUser' => $loginUser,
            'automationClassName' => $automationClassName,
            'disabledAutomation' => $this->disabledAutomation,
        ];
        $body = view('api.emails.automation-disabled', $viewData)->render();
        $data = [
            'body' => $body,
            'to' => $toAddresses,
            'subject' => $subject,
            'from' => $fromAddress,
            'fromName' => 'Clienty CRM',
            'sendDate' => (new DateTime())->format('Y-m-d\TH:i:sP'),
            'appCustomId' => 'SYSTEM_disabled_automation_by_client',
            'appCustomMetadata' => json_encode([
                'login_user_id' => $loginUser->id, 'disabled_automation_id' => $this->disabledAutomation->id
            ]),
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }

        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = resolve(ClientyMailerAPIHelper::class)->scheduleQuickEmail($dto);
    }

}
