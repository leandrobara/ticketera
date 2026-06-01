<?php

namespace App\Jobs\WhatsAppNotificationEvents;

use Throwable;
use Exception;
use App\Models\User;
use App\Models\Lead;
use App\Models\Task;
use App\Helpers\WAPIHelper;
use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use App\Helpers\SimpleEncrypter;
use App\Services\API\UserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\DTO\WAPI\WAPIHelperMessageDTO;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\TaskNotificationWhatsAppMessage;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Jobs\WhatsAppNotificationEvents\Traits\InjectLog;
use App\Services\API\TaskNotificationWhatsAppMessageService;
use App\Services\API\Dispatchers\UserEventsDispatcherService;
use App\Exceptions\Helpers\WAPIHelper\WAPIHelperUserNotSyncedException;
use App\Services\API\Dispatchers\WhatsAppNotificationEventsDispatcherService;


class SendWAPITaskNotificationBeforeExpiresJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;
    
    public $timeout = 120;

    public User $user;
    public Task $task;
    public TaskNotificationWhatsAppMessage $taskNotificationWhatsAppMessage;


    public function __construct(
        public readonly int $userId,
        public readonly int $taskNotificationWhatsAppMessageId,
    ) {
    }


    public function handle()
    {
        $lockKey = 'SendWAPITaskNotificationBeforeExpiresJob:' . $this->userId;


        //
        // FF. WAPI CANCELADO.
        //
        $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);
        $taskNotif = TaskNotificationWhatsAppMessage::findOrFail($this->taskNotificationWhatsAppMessageId);
        $notifsService->persistFailReason($taskNotif, 'WAPI_STOPPED_BY_FF');
        return true;
        //
        

        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, $this->timeout);
        
        $this->user = resolve(UserService::class)->findOrFail($this->userId);
        $this->taskNotificationWhatsAppMessage = TaskNotificationWhatsAppMessage::findOrFail(
            $this->taskNotificationWhatsAppMessageId
        );

        if (!$lockIsGranted) {
            $this->requeueThisJob();
            return true;
        }

        if ($this->user?->wapi_is_paused) {
            $this->requeueThisJob(
                isPausedWapiRequeue: true,
                baseDelaySeconds: $this->user?->wapi_pause_delay_seconds ?? 300,
            );
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            return true;
        }

        try {
            $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);

            $notifHasError = $this->handleAndPersistErrorIfExists($this->taskNotificationWhatsAppMessage);
            if ($notifHasError) {
                return true;
            }

            $task = $this->taskNotificationWhatsAppMessage->task;
            $viewData = ['task' => $task, 'user' => $this->user];
            $viewRoute = 'api.whatsapp-message-notification.task.task-expiring-now';
            $chatMessageString = view($viewRoute, $viewData)->render();

            $WAPIHelperMessageDTO = WAPIHelperMessageDTO::build(
                chatMessage: $chatMessageString,
                phoneNumber: $this->user->wapi_session_phone_number,
                wapiSessionPhoneNumber: $this->user->wapi_session_phone_number,
            );

            $redirectWapi = config('wapi.redirect_wapi', false);
            $redirectWapiToPhone = config('wapi.redirect_wapi_to_phone', null);
            $replaceWAPIFromPhone = config('wapi.replace_wapi_from_phone', null);
            if ($redirectWapi && $redirectWapiToPhone) {
                $WAPIHelperMessageDTO->phoneNumber = $redirectWapiToPhone;
            }
            if ($redirectWapi && $replaceWAPIFromPhone) {
                $WAPIHelperMessageDTO->wapiSessionPhoneNumber = $replaceWAPIFromPhone;
            }

            $WAPIResponse = resolve(WAPIHelper::class, ['user' => $this->user])->sendMessage($WAPIHelperMessageDTO);
            $notifsService->persistSuccessSent($this->taskNotificationWhatsAppMessage);

            resolve(LockHelper::class)->releaseLockByName($lockKey);
        } catch (Exception $e) {
            $notAuth = stripos($e->getMessage(), 'auth_session_does_not_exist') !== false;
            $notAuth = $notAuth || stripos($e->getMessage(), 'whatsapp_client_session_not_authenticated') !== false;
            if ($notAuth || $e instanceof WAPIHelperUserNotSyncedException) {
                // Si en WAPI figura como no vinculado, le deshabilito WAPI en Clienty durante 5 minutos, luego lo
                // habilito nuevamente. Con esto evito que si hay automations o mensajes programados, ralenticen
                // la queue (ya que verificar vinculación tarda unos segundos en WAPI) jodiendo a otros
                // jobs que necesitan correr.
                resolve(UserService::class)->update($this->user, ['wapi_is_synced' => false]);
                // resolve(UserEventsDispatcherService::class)->dispatchEnableUserWAPIJob($this->user, 300);
            }

            $notifsService->persistFailReason($this->taskNotificationWhatsAppMessage, $e->getMessage());
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            throw $e;
        }
    }


    // Devuelve true si hubo error
    protected function handleAndPersistErrorIfExists(TaskNotificationWhatsAppMessage $taskNotif): bool
    {
        $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);

        if (!config('wapi.wapi_notifications_enabled')) {
            $notifsService->persistFailReason($taskNotif, 'disabled_notifications_whatsapp_message');
            return true;
        }
        
        if (!$this->user) {
            $notifsService->persistFailReason($taskNotif, 'user_was_deleted');
            return true;
        }
        if (!$this->user->enabled) {
            $notifsService->persistFailReason($taskNotif, 'user_is_not_enabled');
            return true;
        }
        if (!$this->user->client->enabled) {
            $notifsService->persistFailReason($taskNotif, 'client_is_not_enabled');
            return true;
        }
        if (!$this->user->client->clientSettings->enable_task_hour_reminder_whatsapp_message_alert) {
            $notifsService->persistFailReason($taskNotif, 'disabled_task_hour_reminder_whatsapp_message_alert');
            return true;
        }
        if (!$this->user->client->clientSettings->enable_wapi) {
            $notifsService->persistFailReason($taskNotif, 'wapi_is_not_enabled');
            return true;
        }
        if (!$this->user->wapi_session_phone_number) {
            $notifsService->persistFailReason($taskNotif, 'user_is_not_synced_with_wapi');
            return true;
        }
        if (!$this->user->wapi_is_synced) {
            $notifsService->persistFailReason($taskNotif, 'user_is_not_synced_with_wapi');
            return true;
        }

        // Filtro de prevención
        $filteredNotificationsCount = $taskNotif
            ->whereNull('sent_date')
            ->whereNull('exception')
            ->where('type', 'expires_now')
            ->where('success', false)
            ->whereNotNull('send_date')
            ->whereNotNull('dispatched_date')
            ->count()
        ;
        if (!$taskNotif->task) {
            $notifsService->persistFailReason($taskNotif, 'task_does_not_exist');
            return true;
        }

        return false;
    }


    protected function requeueThisJob(int $baseDelaySeconds = 20, bool $isPausedWapiRequeue = false): void
    {
        resolve(WhatsAppNotificationEventsDispatcherService::class)->dispatchSendWAPITaskNotificationBeforeExpiresJob(
            $this->user, $this->taskNotificationWhatsAppMessage, $baseDelaySeconds
        );
        // Delete the current job
        $this->delete();
    }

}
