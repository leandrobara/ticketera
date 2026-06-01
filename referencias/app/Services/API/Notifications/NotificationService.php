<?php

namespace App\Services\API\Notifications;

use DateTime;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Notification;
use App\Models\AutomationLog;
use App\Models\WAutomationLog;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\WhatsAppSendingMessage;
use App\Helpers\ClientyMailerAPIHelper;
use App\Models\WhatsAppMetaAPIConnection;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;


class NotificationService
{

    private $notificationRepository;


    public function __construct(Repository $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }


    public function storeAutomationEmailSendingEmailError(AutomationLog $automationLog): Notification
    {
        $data = [
            'lead_id' => $automationLog->lead->id,
            'client_id' => $automationLog->client_id,
            'automation_log_id' => $automationLog->id,
            'user_id' => $automationLog->lead->user_id,
            'type' => Notification::TYPE_USER_EMAIL_SENDING_NOT_ENABLED,
        ];
        $notification = $this->create($data);
        return $notification;
    }


    public function storeWAPISyncError(
        int $leadId,
        int $userId,
        int $clientId,
        WAutomationLog $wAutomationLog,
    ): Notification {
        $data = [
            'lead_id' => $leadId,
            'user_id' => $userId,
            'client_id' => $clientId,
            'wautomation_log_id' => $wAutomationLog->id,
            'type' => Notification::TYPE_USER_WAPI_NOT_SYNCED,
        ];
        $notification = $this->create($data);
        return $notification;
    }


    public function storeWhatsAppMetaAPINotSyncedError(WhatsAppMetaAPIConnection $connection): Notification
    {
        return $this->create([
            'user_id' => $connection->user_id,
            'client_id' => $connection->client_id,
            'type' => Notification::TYPE_USER_WHATSAPP_META_API_NOT_SYNCED,
        ]);
    }


    public function create(array $data): Notification
    {
        return $this->notificationRepository->create($data);
    }


    public function findOneByTypeAndUser(string $type, User $user): ?Notification
    {
        return $this->notificationRepository->findOneByTypeAndUser($type, $user);
    }


    public function findOneByTypeAndClient(string $type, Client $client): ?Notification
    {
        return $this->notificationRepository->findOneByTypeAndClient($type, $client);
    }


    public function findByTypeAndClient(string $type, Client $client): ?Notification
    {
        return $this->notificationRepository->findByTypeAndClient($type, $client);
    }


    public function listClientNotificationsByUser(User $user): Collection
    {
        $notifications = $this->notificationRepository->listClientNotifications($user->client);
        return $notifications;
    }


    public function findOneUserEmailSendingNotEnabled(User $user): ?Notification
    {
        $type = Notification::TYPE_USER_EMAIL_SENDING_NOT_ENABLED;
        return $this->findOneByTypeAndUser($type, $user);
    }


    public function findWAPINotSyncedByUser(User $user): Collection
    {
        return $this->notificationRepository->findWAPINotSyncedByUser($user);
    }


    public function findWhatsAppMetaAPINotSyncedByUser(User $user): Collection
    {
        return $this->notificationRepository->findWhatsAppMetaAPINotSyncedByUser($user);
    }


    public function findEmailSendingNotEnabledByUser(User $user): Collection
    {
        return $this->notificationRepository->findEmailSendingNotEnabledByUser($user);
    }


    public function delete(Notification $notification): Notification
    {
        return $this->notificationRepository->delete($notification);
    }


    public function deleteNotifications(Collection $notifications, array $opts = []): Collection
    {
        $deletedNotifications = collect([]);
        foreach ($notifications as $notification) {
            $deletedNotification = $this->notificationRepository->delete($notification, $opts);
            $deletedNotifications->push($deletedNotification);
        }
        return $deletedNotifications;
    }


    public function sendWhatsAppMetaAPINotSyncedEmail(
        Notification $notification,
        WhatsAppMetaAPIConnection $connection,
        string $errorDescription
    ): void {
        $user = $connection->user;
        $client = $connection->client;

        $fromEmail = config('emails.leads_notification_from_email');
        $toAddresses = redirectEmails()
            ? [config('emails.redirect_emails_to')]
            : config('emails.user_notification_emails_error_report_to')
        ;

        $subject = "Cliente '{$client->name}' | Conexión WhatsApp Meta API desconectada";
        $body = view('api.emails.notifications.whatsapp-meta-api-not-synced', compact(
            'notification', 'connection', 'client', 'user', 'errorDescription'
        ))->render();

        $appCustomMetadata = json_encode([
            'notificationId' => $notification->id, 'whatsAppMetaAPIConnectionId' => $connection->id,
        ]);

        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray([
            'body' => $body,
            'subject' => $subject,
            'to' => $toAddresses,
            'from' => $fromEmail,
            'fromName' => 'Clienty CRM',
            'appCustomMetadata' => $appCustomMetadata,
            'sendDate' => (new DateTime())->format('Y-m-d\TH:i:sP'),
            'appCustomId' => "SYSTEM_wap_meta_api_not_synced_notif_{$notification->id}",
        ]);

        resolve(ClientyMailerAPIHelper::class)->scheduleQuickEmail($dto);
    }

}
