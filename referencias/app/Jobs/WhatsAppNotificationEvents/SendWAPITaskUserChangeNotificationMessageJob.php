<?php

namespace App\Jobs\WhatsAppNotificationEvents;

use Throwable;
use Exception;
use App\Models\User;
use App\Models\Lead;
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
use App\Services\API\Dispatchers\WhatsAppNotificationEventsDispatcherService;
    

class SendWAPITaskUserChangeNotificationMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;
    
    public $timeout = 120;


    public function __construct(
        public readonly int $taskNotificationWhatsAppMessageId,
        public readonly int $olduserId,
        public readonly int $assignerUserId,
    ) {
    }


    public function handle()
    {
        $lockKey = 'SendWAPITaskUserChangeNotificationMessageJob';

        
        //
        // FF. WAPI CANCELADO.
        //
        $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);
        $taskNotif = TaskNotificationWhatsAppMessage::findOrFail($this->taskNotificationWhatsAppMessageId);
        $notifsService->persistFailReason($taskNotif, 'WAPI_STOPPED_BY_FF');
        return true;
        //


        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, $this->timeout);
        if (!$lockIsGranted) {
            dump('REQUEUED');
            $this->requeueThisJob();
            return true;
        }
        if (!$this->taskNotificationWhatsAppMessageId) {
            dump('No taskNotificationWhatsAppMessageId');
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            return true;
        }

        $oldUser = resolve(UserService::class)->find($this->olduserId);

        try {
            $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);
            $assignerUser = resolve(UserService::class)->findOrFail($this->assignerUserId);
            $taskNotif = TaskNotificationWhatsAppMessage::findOrFail($this->taskNotificationWhatsAppMessageId);
            
            $notifHasError = $this->handleAndPersistErrorIfExists($taskNotif);
            if ($notifHasError) {
                resolve(LockHelper::class)->releaseLockByName($lockKey);
                return true;
            }

            $user = $taskNotif->task->user;
            if ($user?->wapi_is_paused) {
                $this->requeueThisJob(
                    isPausedWapiRequeue: true,
                    baseDelaySeconds: $user?->wapi_pause_delay_seconds ?? 300,
                );
                resolve(LockHelper::class)->releaseLockByName($lockKey);
                return true;
            }

            $task = $taskNotif->task;
            $lead = $task->lead;
            $client = $taskNotif->client;

            $encryptedLeadId = SimpleEncrypter::encryptInt($lead->id);
            $leadUrl = clientUrl($client, "/?eli={$encryptedLeadId}");
            $taskUrl = clientUrl($client, '/tasks');
            
            $chatMessageString = view(
                'api.whatsapp-message-notification.task.task-user-change',
                compact('task', 'user', 'lead', 'oldUser', 'taskUrl', 'leadUrl', 'assignerUser')
            )->render();

            $WAPIHelperMessageDTO = WAPIHelperMessageDTO::build(
                chatMessage: $chatMessageString,
                phoneNumber: $user->wapi_session_phone_number,
                wapiSessionPhoneNumber: $user->wapi_session_phone_number,
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

            $WAPIResponse = resolve(WAPIHelper::class, ['user' => $user])->sendMessage($WAPIHelperMessageDTO);

            $notifsService->persistSuccessSent($taskNotif);
            
            resolve(LockHelper::class)->releaseLockByName($lockKey);
        } catch (Exception $e) {
            $notifsService->persistFailReason($taskNotif, $e->getMessage());
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

        // No marco error: si esto pasa, pudo haber sido un automation de new lead.
        if ($taskNotif->do_not_send) {
            dump('task_notification_has_do_not_send_flag');
            return true;
        }
        // Si ya tiene un error guardado, no lo piso con otro.
        if ($taskNotif->exception) {
            dump('notification_already_has_an_exception');
            return true;
        }

        if (!$taskNotif->dispatched_date) {
            $notifsService->persistFailReason($taskNotif, 'task_notification_has_no_dispatched_date');
            return true;
        }
        if ($taskNotif->sent_date) {
            $notifsService->persistFailReason($taskNotif, 'task_notification_has_sent_date');
            return true;
        }
        if (!$taskNotif->send_date) {
            $notifsService->persistFailReason($taskNotif, 'task_notification_does_not_have_send_date');
            return true;
        }
        if ($taskNotif->success) {
            $notifsService->persistFailReason($taskNotif, 'task_notification_has_success_flag');
            return true;
        }
        
        if (!$taskNotif->task) {
            $this->persistFailReason($taskNotif, 'task_was_deleted');
            return true;
        }
        if (!$taskNotif->user) {
            $this->persistFailReason($taskNotif, 'user_was_deleted');
            return true;
        }
        if (!$taskNotif->user->enabled) {
            $this->persistFailReason($taskNotif, 'user_is_not_enabled');
            return true;
        }
        if (!$taskNotif->client->clientSettings->enable_task_user_change_whatsapp_message_alert) {
            $notifsService->persistFailReason($taskNotif, 'disabled_task_user_change_whatsapp_message');
            return true;
        }

        if (!$taskNotif->client->clientSettings->enable_wapi) {
            $notifsService->persistFailReason($taskNotif, 'wapi_is_not_enabled');
            return true;
        }
        if (!$taskNotif->user->wapi_session_phone_number) {
            $notifsService->persistFailReason($taskNotif, 'user_is_not_synced_with_wapi');
            return true;
        }
        if (!$taskNotif->user->wapi_is_synced) {
            $notifsService->persistFailReason($taskNotif, 'user_is_not_synced_with_wapi');
            return true;
        }

        return false;
    }


    protected function requeueThisJob(int $baseDelaySeconds = 20, bool $isPausedWapiRequeue = false): void
    {
        $taskNotif = TaskNotificationWhatsAppMessage::findOrFail($this->taskNotificationWhatsAppMessageId);
        $service = resolve(WhatsAppNotificationEventsDispatcherService::class);
        $service->dispatchSendWAPITaskUserChangeNotificationMessageJob($taskNotif, $baseDelaySeconds);
        // Delete the current job
        $this->delete();
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
