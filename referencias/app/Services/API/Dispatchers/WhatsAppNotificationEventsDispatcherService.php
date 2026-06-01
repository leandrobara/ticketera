<?php

namespace App\Services\API\Dispatchers;

use App\Models\User;
use App\Models\Client;
use App\Models\WhatsAppSending;
use Illuminate\Support\Collection;
use App\Models\WhatsAppSendingMessage;
use App\Services\Traits\CustomDispatch;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Models\TaskNotificationWhatsAppMessage;
use App\Models\LeadNotificationWhatsAppMessage;
use App\Jobs\WhatsAppNotificationEvents\SendWAPILeadNotificationMessageJob;
use App\Jobs\WhatsAppNotificationEvents\SendWAPINewTaskNotificationMessageJob;
use App\Jobs\WhatsAppNotificationEvents\SendWAPITaskNotificationExpiringTodayJob;
use App\Jobs\WhatsAppNotificationEvents\SendWAPITaskNotificationBeforeExpiresJob;
use App\Jobs\WhatsAppNotificationEvents\SendWAPIGroupedLeadsNotificationMessageJob;
use App\Jobs\WhatsAppNotificationEvents\SendWAPITaskUserChangeNotificationMessageJob;


class WhatsAppNotificationEventsDispatcherService
{

    use CustomDispatch;

    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchSendWAPILeadNotificationMessageJob(
        LeadNotificationWhatsAppMessage $leadNotificationWhatsAppMessage,
        int $delaySecs = 0
    ): void {
        $params = [$leadNotificationWhatsAppMessage->id];
        $this->doCustomDispatch(
            SendWAPILeadNotificationMessageJob::class, $params, $delaySecs, $leadNotificationWhatsAppMessage->client_id
        );
    }


    public function dispatchSendWAPIGroupedLeadsNotificationMessageJob(
        User $user,
        Collection $leadNotificationsWhatsApp,
        int $delaySecs = 0
    ): void {
        $params = [$user->id, $leadNotificationsWhatsApp->pluck('id')->toArray()];
        $this->doCustomDispatch(
            SendWAPIGroupedLeadsNotificationMessageJob::class, $params, $delaySecs, $user->client_id
        );
    }


    // Manda un mensaje diario con las tareas a vencer ese día
    public function dispatchSendWAPITasksNotificationExpiringTodayJob(
        User $user,
        Collection $taskNotificationsWhatsApp,
        int $delaySecs = 0
    ): void {
        $params = [$user->id, $taskNotificationsWhatsApp->pluck('id')->toArray()];
        $this->doCustomDispatch(
            SendWAPITaskNotificationExpiringTodayJob::class, $params, $delaySecs, $user->client_id
        );
    }


    // Manda un mensaje unos minutos antes de que venza la tarea
    public function dispatchSendWAPITaskNotificationBeforeExpiresJob(
        User $user,
        TaskNotificationWhatsAppMessage $taskNotificationWhatsApp,
        int $delaySecs = 0
    ): void {
        $params = [$user->id, $taskNotificationWhatsApp->id];
        $this->doCustomDispatch(
            SendWAPITaskNotificationBeforeExpiresJob::class, $params, $delaySecs, $user->client_id
        );
    }


    public function dispatchSendWAPINewTaskNotificationMessageJob(
        TaskNotificationWhatsAppMessage $taskNotificationWhatsAppMessage,
        User $assignerUser,
        int $delaySecs = 0
    ): void {
        $params = [$taskNotificationWhatsAppMessage->id, $assignerUser->id];
        $clientId = $taskNotificationWhatsAppMessage->client_id;
        $this->doCustomDispatch(SendWAPINewTaskNotificationMessageJob::class, $params, $delaySecs, $clientId);
    }


    public function dispatchSendWAPITaskUserChangeNotificationMessageJob(
        TaskNotificationWhatsAppMessage $taskNotificationWhatsAppMessage,
        User $oldUser,
        User $assignerUser,
        int $delaySecs = 0
    ): void {
        $params = [$taskNotificationWhatsAppMessage->id, $oldUser->id, $assignerUser->id];
        $clientId = $taskNotificationWhatsAppMessage->client_id;
        $this->doCustomDispatch(SendWAPITaskUserChangeNotificationMessageJob::class, $params, $delaySecs, $clientId);
    }

}
