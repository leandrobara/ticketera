<?php

namespace App\Services\API;

use DateTime;
use App\Models\User;
use App\Models\Client;
use App\Models\UserNotification;
use App\Helpers\MondayAPIHelper2;
use App\Helpers\ClientyMailerAPIHelper;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\UserNotificationRepository;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;


class UserNotificationService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        private readonly string $notificationFromEmail,
        private readonly MondayAPIHelper2 $mondayAPIHelper,
        private readonly ClientyMailerAPIHelper $clientyMailerAPIHelper,
        private readonly UserNotificationRepository $userNotificationRepository
    ) {
    }


    public function findLastUnsubscribeByClientId(int $clientId): ?UserNotification
    {
        return $this->userNotificationRepository->findLastUnsubscribeByClientId($clientId);
    }


    public function sendNotification(array $data): UserNotification
    {
        $dateNow = new DateTime();
        $user = $this->getUser();
        $client = $this->getClient();
        $notificationType = $data['notification_type'] ?? '';
        $isUnsubscribeRequest = $notificationType == 'unsubscribe';
        $userNotification = $this->userNotificationRepository->create($client, $user, $data);
        $typeDescription = $userNotification->typeDescription;
        
        $subject = 'Clienty CRM | IMPORTANTE - Contacto: "' . $typeDescription . '"';
        $toAddresses = $this->getToAddressesEmailsByNotificationType($notificationType);
        $body = view('api.emails.user-notification.body', compact('userNotification'))->render();
        if ($isUnsubscribeRequest) {
            $subject = 'Clienty CRM | SOLICITUD DE BAJA - Cliente: ' . $client->name;
            $body = view('api.emails.user-notification.unsubscribe-body', compact('userNotification'))->render();
        }

        $data = [
            'body' => $body,
            'to' => $toAddresses,
            'subject' => $subject,
            'fromName' => 'Clienty CRM',
            'from' => $this->notificationFromEmail,
            'sendDate' => $dateNow->format('Y-m-d\TH:i:sP'),
            'appCustomId' => 'SYSTEM_new_user_notification_' . $userNotification->id,
            'appCustomMetadata' => json_encode(['id' => $userNotification->id]),
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }

        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);

        $userNotification = $this->userNotificationRepository->update($userNotification, [
            'external_email_id' => $mailerResponseDTO->id,
            'scheduled_date' => $dateNow->format('Y-m-d H:i:s'),
        ]);

        if ($isUnsubscribeRequest) {
            $this->createMondayUnsubscribeItem($client, $userNotification);
        }

        return $userNotification;
    }


    public function markAsSent(int $userNotificationId, DateTime $sentDate)
    {
        $userNotification = $this->userNotificationRepository->findById($userNotificationId);
        $userNotification = $this->userNotificationRepository->update($userNotification, [
            'sent_date' => $sentDate->format('Y-m-d H:i:s')
        ]);
        return $userNotification;
    }


    private function getToAddressesEmailsByNotificationType(string $notificationType): array
    {
        $recipientsAddresses = [
            'unsubscribe' => config('emails.user_notification_emails_unsubscribe_to'),
            'error_report' => config('emails.user_notification_emails_error_report_to'),
            'need_callback' => config('emails.user_notification_emails_need_callback_to'),
            'need_more_users' => config('emails.user_notification_emails_need_more_users_to'),
            'need_more_email_sending_quota' =>
                config('emails.user_notification_emails_need_more_email_sending_quota_to'),
        ];

        return $recipientsAddresses[$notificationType] ?? config('emails.user_notification_emails_to');
    }


    private function createMondayUnsubscribeItem(Client $client, UserNotification $userNotification): array
    {
        $unsubscribeReason = $userNotification->unsubscribeReasons[$userNotification->unsubscribe_reason] ?? '-';
        
        $itemName = $client->name;
        $columnValues = ['motivo__1' => "{$unsubscribeReason}: {$userNotification->comments}"];
        // motivo__1 es el ID de la columna [Motivo y actualizaciones]
        
        $created = $this->mondayAPIHelper->createUnsubscribeItem($itemName, $columnValues);
        return $created;
    }

}
