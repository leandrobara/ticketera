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
use App\Models\LeadNotificationWhatsAppMessage;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Jobs\WhatsAppNotificationEvents\Traits\InjectLog;
use App\Services\API\LeadNotificationWhatsAppMessageService;
use App\Services\API\Dispatchers\WhatsAppNotificationEventsDispatcherService;
    

//
// @todo Cambiar nombre a SendWAPINewLeadNotificationMessageJob
//
class SendWAPILeadNotificationMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;
    
    public $timeout = 120;


    public function __construct(
        public readonly int $leadNotificationWhatsAppMessageId,
    ) {
    }


    public function handle()
    {
        $lockKey = 'SendWAPILeadNotificationMessageJob';



        //
        // FF. WAPI CANCELADO.
        //
        $notifsService = resolve(LeadNotificationWhatsAppMessageService::class);
        $leadNotif = LeadNotificationWhatsAppMessage::findOrFail($this->leadNotificationWhatsAppMessageId);
        $notifsService->persistFailReason($leadNotif, 'WAPI_STOPPED_BY_FF');
        return true;
        //



        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, $this->timeout);
        if (!$lockIsGranted) {
            dump('REQUEUED');
            $this->requeueThisJob();
            return true;
        }
        if (!$this->leadNotificationWhatsAppMessageId) {
            dump('No leadNotificationWhatsAppMessageId');
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            return true;
        }

        try {
            $notifsService = resolve(LeadNotificationWhatsAppMessageService::class);
            $leadNotif = LeadNotificationWhatsAppMessage::findOrFail($this->leadNotificationWhatsAppMessageId);
            
            $notifHasError = $this->handleAndPersistErrorIfExists($leadNotif);
            if ($notifHasError) {
                resolve(LockHelper::class)->releaseLockByName($lockKey);
                return true;
            }

            $user = $leadNotif->lead->user;
            if ($user?->wapi_is_paused) {
                $this->requeueThisJob(
                    isPausedWapiRequeue: true,
                    baseDelaySeconds: $user?->wapi_pause_delay_seconds ?? 300,
                );
                resolve(LockHelper::class)->releaseLockByName($lockKey);
                return true;
            }

            $encryptedLeadId = SimpleEncrypter::encryptInt($leadNotif->lead->id);
            $directLeadUrl = clientUrl($leadNotif->lead->client, "/?eli={$encryptedLeadId}");
            
            $viewParams = ['lead' => $leadNotif->lead, 'directLeadUrl' => $directLeadUrl];
            $viewRoute = 'api.whatsapp-message-notification.new-lead.notification-message';
            $chatMessageString = view($viewRoute, $viewParams)->render();

            $WAPIHelperMessageDTO = WAPIHelperMessageDTO::build(
                chatMessage: $chatMessageString,
                phoneNumber: $user->wapi_session_phone_number,
                wapiSessionPhoneNumber: $user->wapi_session_phone_number,
                // phoneNumber: $user->phone,
                // wapiSessionPhoneNumber: config('wapi.wapi_notifications_session_phone_number'),
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

            $notifsService->persistSuccessSent($leadNotif);
            
            resolve(LockHelper::class)->releaseLockByName($lockKey);
        } catch (Exception $e) {
            $notifsService->persistFailReason($leadNotif, $e->getMessage());
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            throw $e;
        }
    }


    // Devuelve true si hubo error
    protected function handleAndPersistErrorIfExists(LeadNotificationWhatsAppMessage $leadNotif): bool
    {
        $notifsService = resolve(LeadNotificationWhatsAppMessageService::class);

        if (!config('wapi.wapi_notifications_enabled')) {
            $notifsService->persistFailReason($leadNotif, 'disabled_notifications_whatsapp_message');
            return true;
        }

        // Exception para evitar envíos
        // $notifsService->persistFailReason($leadNotif, '__WAP_NOTIFICATIONS_JOBS_MANUALLY_DISABLED__');
        // return true;

        // No marco error: si esto pasa, pudo haber sido un automation de new lead.
        if ($leadNotif->do_not_send) {
            dump('lead_notification_has_do_not_send_flag');
            return true;
        }
        if ($leadNotif->is_grouped) {
            dump('lead_notification_has_grouped_flag');
            return true;
        }
        // Si ya tiene un error guardado, no lo piso con otro.
        if ($leadNotif->exception) {
            dump('notification_already_has_an_exception');
            return true;
        }

        if (!$leadNotif->dispatched_date) {
            $notifsService->persistFailReason($leadNotif, 'lead_notification_has_no_dispatched_date');
            return true;
        }
        if ($leadNotif->sent_date) {
            $notifsService->persistFailReason($leadNotif, 'lead_notification_has_sent_date');
            return true;
        }
        if (!$leadNotif->send_date) {
            $notifsService->persistFailReason($leadNotif, 'lead_notification_does_not_have_send_date');
            return true;
        }
        if ($leadNotif->success) {
            $notifsService->persistFailReason($leadNotif, 'lead_notification_has_success_flag');
            return true;
        }
        
        if (!$leadNotif->lead) {
            $this->persistFailReason($leadNotif, 'lead_was_deleted');
            return true;
        }
        if (!$leadNotif->lead->user) {
            $this->persistFailReason($leadNotif, 'user_was_deleted');
            return true;
        }
        if (!$leadNotif->lead->user->enabled) {
            $this->persistFailReason($leadNotif, 'user_is_not_enabled');
            return true;
        }
        // if (!$leadNotif->lead->user->phone) {
        //     $this->persistFailReason($leadNotif, 'user_has_no_phone');
        //     return true;
        // }
        if (!$leadNotif->client->clientSettings->enable_new_lead_whatsapp_message_alert) {
            $notifsService->persistFailReason($leadNotif, 'disabled_new_lead_whatsapp_message_alert');
            return true;
        }

        if (!$leadNotif->client->clientSettings->enable_wapi) {
            $notifsService->persistFailReason($leadNotif, 'wapi_is_not_enabled');
            return true;
        }
        if (!$leadNotif->lead->user->wapi_session_phone_number) {
            $notifsService->persistFailReason($leadNotif, 'user_is_not_synced_with_wapi');
            return true;
        }
        if (!$leadNotif->lead->user->wapi_is_synced) {
            $notifsService->persistFailReason($leadNotif, 'user_is_not_synced_with_wapi');
            return true;
        }

        return false;
    }


    protected function requeueThisJob(int $baseDelaySeconds = 1, bool $isPausedWapiRequeue = false): void
    {
        $leadNotif = LeadNotificationWhatsAppMessage::findOrFail($this->leadNotificationWhatsAppMessageId);
        resolve(WhatsAppNotificationEventsDispatcherService::class)->dispatchSendWAPILeadNotificationMessageJob(
            $leadNotif, $baseDelaySeconds
        );
        // Delete the current job
        $this->delete();
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
