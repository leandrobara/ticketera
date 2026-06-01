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


class SendDisabledUserEmailJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    
    public function __construct(public readonly int $disabledUserId, public readonly int $loginUserId)
    {
    }


    public function handle()
    {
        $loginUser = resolve(UserService::class)->findOrFail($this->loginUserId);
        $disabledUser = resolve(UserService::class)->findOrFail($this->disabledUserId);

        $enabledUsersCount = $disabledUser->client->enabledUsers->count();
        $acquiredUsersCount = $disabledUser->client->clientSettings->acquired_users;
        if ($enabledUsersCount >= $acquiredUsersCount) {
            return true;
        }

        $clientName = $loginUser->client->name;
        $subject = "Clienty CRM | {$clientName} - Usuario inhabilitado";
        
        $fromAddress = config('emails.leads_notification_from_email');
        $toAddresses = config('emails.user_notification_emails_user_actions_to');
        $usersConfigPageUrl = clientUrl($loginUser->client, '/configurations/users');

        $viewData = [
            'loginUser' => $loginUser,
            'disabledUser' => $disabledUser,
            'enabledUsersCount' => $enabledUsersCount,
            'acquiredUsersCount' => $acquiredUsersCount,
            'usersConfigPageUrl' => $usersConfigPageUrl,
        ];
        $body = view('api.emails.user-disabled', $viewData)->render();

        $data = [
            'body' => $body,
            'to' => $toAddresses,
            'subject' => $subject,
            'from' => $fromAddress,
            'fromName' => 'Clienty CRM',
            'appCustomId' => 'SYSTEM_disabled_user',
            'sendDate' => (new DateTime())->format('Y-m-d\TH:i:sP'),
            'appCustomMetadata' => json_encode([
                'login_user_id' => $loginUser->id, 'disabled_user_id' => $disabledUser->id,
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
