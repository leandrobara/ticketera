<?php

namespace App\Jobs\EmailEvents;

use DateTime;
use Throwable;
use Exception;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\EmailEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;


class SendCreatedUserEmailJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    

    public function __construct(public readonly int $createdUserId, public readonly int $loginUserId)
    {
    }


    public function handle()
    {
        $loginUser = resolve(UserService::class)->findOrFail($this->loginUserId);
        $createdUser = resolve(UserService::class)->findOrFail($this->createdUserId);

        $enabledUsersCount = $createdUser->client->enabledUsers->count();
        $acquiredUsersCount = $createdUser->client->clientSettings->acquired_users;
        if ($enabledUsersCount <= $acquiredUsersCount) {
            return true;
        }

        $clientName = $createdUser->client->name;
        $subject = "Clienty CRM | {$clientName} - Nuevo usuario creado";
        
        $fromAddress = config('emails.leads_notification_from_email');
        $toAddresses = config('emails.user_notification_emails_user_actions_to');
        $usersConfigPageUrl = clientUrl($createdUser->client, '/configurations/users');

        $viewData = [
            'loginUser' => $loginUser,
            'createdUser' => $createdUser,
            'enabledUsersCount' => $enabledUsersCount,
            'usersConfigPageUrl' => $usersConfigPageUrl,
            'acquiredUsersCount' => $acquiredUsersCount,
        ];
        $body = view('api.emails.user-created', $viewData)->render();

        $data = [
            'body' => $body,
            'to' => $toAddresses,
            'subject' => $subject,
            'from' => $fromAddress,
            'fromName' => 'Clienty CRM',
            'appCustomId' => 'SYSTEM_created_user',
            'sendDate' => (new DateTime())->format('Y-m-d\TH:i:sP'),
            'appCustomMetadata' => json_encode([
                'login_user_id' => $loginUser->id, 'created_user_id' => $createdUser->id,
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
