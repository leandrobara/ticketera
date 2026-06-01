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
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Jobs\WhatsAppNotificationEvents\Traits\InjectLog;
use App\Services\API\TaskNotificationWhatsAppMessageService;
use App\Services\API\Dispatchers\WhatsAppNotificationEventsDispatcherService;


// Deprecado, no se va a usar
class SendWAPITaskNotificationExpiringTodayJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;
    
    public ?User $user;
    public $timeout = 120;


    public function __construct(
        public readonly int $userId,
        public readonly array $taskNotificationWhatsAppMessageIds,
    ) {
    }


    public function handle()
    {
        // Deprecado, no se va a usar
        return true;


        // $lockKey = 'SendWAPITaskNotificationExpiringTodayJob:' . $this->userId;
        // $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, $this->timeout);
        // if (!$lockIsGranted) {
        //     dump('REQUEUED');
        //     $this->requeueThisJob();
        //     return true;
        // }
        // if (!$this->taskNotificationWhatsAppMessageIds) {
        //     dump('No taskNotificationWhatsAppMessageIds');
        //     return true;
        // }


        // try {
        //     $this->user = resolve(UserService::class)->find($this->userId);
        //     $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);
        //     $taskNotifs = $notifsService->findByIds(new Collection($this->taskNotificationWhatsAppMessageIds));
            
        //     $notifHasError = $this->handleAndPersistErrorIfExists($taskNotifs);
        //     if ($notifHasError) {
        //         return true;
        //     }

        //     $tasks = $taskNotifs->map(fn ($notif) => $notif->task);
        //     $viewData = ['tasksExpiringToday' => $tasks, 'user' => $this->user];
        //     $viewRoute = 'api.whatsapp-message-notification.task.tasks-expiring-today';
        //     $chatMessageString = view($viewRoute, $viewData)->render();
        //     $chatMessageString = trim(preg_replace('/[ ]+/', ' ', $chatMessageString));
        //     $chatMessageString = str_ireplace(' *ID', '*ID', $chatMessageString); // hotfix espacio en blanco

        //     $WAPIHelperMessageDTO = WAPIHelperMessageDTO::build(
        //         chatMessage: $chatMessageString,
        //         phoneNumber: $this->user->phone,
        //         wapiSessionPhoneNumber: config('wapi.wapi_notifications_session_phone_number'),
        //     );

        //     $redirectWapi = config('wapi.redirect_wapi', false);
        //     $redirectWapiToPhone = config('wapi.redirect_wapi_to_phone', null);
        //     if ($redirectWapi && $redirectWapiToPhone) {
        //         $WAPIHelperMessageDTO->phoneNumber = $redirectWapiToPhone;
        //     }

        //     $WAPIResponse = resolve(WAPIHelper::class, ['user' => $this->user])->sendMessage($WAPIHelperMessageDTO);
        //     $notifsService->persistSuccessSent($taskNotifs);

        //     resolve(LockHelper::class)->releaseLockByName($lockKey);
        // } catch (Exception $e) {
        //     resolve(LockHelper::class)->releaseLockByName($lockKey);
        //     $notifsService->persistFailReason($taskNotifs, $e->getMessage());
        //     throw $e;
        // }
    }


    // Devuelve true si hubo error
    // protected function handleAndPersistErrorIfExists(Collection $taskNotifs): bool
    // {
    //     $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);

    //     if (!config('wapi.wapi_notifications_enabled')) {
    //         $notifsService->persistFailReason($taskNotifs, 'wapi_notifications_is_not_enabled');
    //         return true;
    //     }

    //     if (!$this->user) {
    //         $notifsService->persistFailReason($taskNotifs, 'user_was_deleted');
    //         return true;
    //     }
    //     if (!$this->user->enabled) {
    //         $notifsService->persistFailReason($taskNotifs, 'user_is_not_enabled');
    //         return true;
    //     }
    //     if (!$this->user->phone) {
    //         $notifsService->persistFailReason($taskNotifs, 'user_has_no_phone');
    //         return true;
    //     }
    //     if ($taskNotifs->pluck('task.user_id')->unique()->count() > 1) {
    //         $notifsService->persistFailReason($taskNotifs, 'grouped_notifications_belong_to_multiple_users');
    //         return true;
    //     }
    //     if ($taskNotifs->first()->task->user_id != $this->user->id) {
    //         $notifsService->persistFailReason($taskNotifs, 'grouped_notifications_user_does_not_match');
    //         return true;
    //     }

    //     if (!$this->user->client->enabled) {
    //         $notifsService->persistFailReason($taskNotifs, 'client_is_not_enabled');
    //         return true;
    //     }
    //     if (!$this->user->client->clientSettings->enable_daily_task_whatsapp_message_alert) {
    //         $notifsService->persistFailReason($taskNotifs, 'disabled_daily_task_whatsapp_message_alert');
    //         return true;
    //     }

    //     // Filtro de prevención
    //     $filteredNotificationsCount = $taskNotifs
    //         ->whereNull('sent_date')
    //         ->whereNull('exception')
    //         ->where('type', 'daily')
    //         ->where('success', false)
    //         ->whereNotNull('send_date')
    //         ->whereNotNull('dispatched_date')
    //         ->count()
    //     ;
    //     if ($taskNotifs->count() != $filteredNotificationsCount) {
    //         $notifsService->persistFailReason($taskNotifs, 'task_notifications_matching_error');
    //         return true;
    //     }

    //     $tasks = $taskNotifs->map(fn ($notif) => $notif->task);
    //     if ($taskNotifs->count() != $tasks->count()) {
    //         $notifsService->persistFailReason($taskNotifs, 'tasks_count_matching_error');
    //         return true;
    //     }

    //     return false;
    // }


    // protected function requeueThisJob(): void
    // {
    //     $delaySecs = 20;
    //     $user = resolve(UserService::class)->find($this->userId);
    //     $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);
    //     $taskNotificationsWhatsApp = $notifsService->findByIds(new Collection($this->taskNotificationsWhatsAppIds));

    //     resolve(WhatsAppNotificationEventsDispatcherService::class)->dispatchSendWAPITasksNotificationExpiringTodayJob(
    //         $user, $taskNotificationsWhatsApp, $delaySecs
    //     );
    //     // Delete the current job
    //     $this->delete();
    // }

}
