<?php

namespace App\Jobs\ClientEvents;

use DateTime;
use Throwable;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\ClientEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;


class SendDeletedLeadsNotificationJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;


    public function __construct(
        protected readonly int $userId,
        protected readonly ?string $userIp,
        protected readonly int $deletedLeadsCount,
    ) {
    }


    public function handle()
    {
        $user = resolve(UserService::class)->findOrFail($this->userId);
        $adminUsers = $user->client->users->where('type', 'admin')->where('enabled', true);
        $adminUsersEnabled = $adminUsers->where('enabled_delete_leads_emails_reception', true);
        $toAddresses = $adminUsersEnabled->pluck('email')->unique()->values()->toArray();
        if (!$toAddresses) {
            return true;
        }
        
        $viewData = [
            'user' => $user,
            'userIp' => $this->userIp,
            'deletedLeadsCount' => $this->deletedLeadsCount,
        ];
        $fromAddress = config('emails.leads_notification_from_email');
        $subject = "Clienty CRM | Aviso de eliminación de prospectos";
        $body = view('api.emails.deleted-leads-notification.body', $viewData)->render();
        
        $data = [
            'body' => $body,
            'to' => $toAddresses,
            'subject' => $subject,
            'from' => $fromAddress,
            'fromName' => 'Clienty CRM',
            'appCustomId' => 'SYSTEM_deleted_leads_notification',
            'sendDate' => (new DateTime())->format('Y-m-d\TH:i:sP'),
            'appCustomMetadata' => json_encode([
                'user_id' => $user->id,
                'userIp' => $this->userIp,
                'deletedLeadsCount' => $this->deletedLeadsCount,
            ]),
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }

        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = resolve(ClientyMailerAPIHelper::class)->scheduleQuickEmail($dto);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
