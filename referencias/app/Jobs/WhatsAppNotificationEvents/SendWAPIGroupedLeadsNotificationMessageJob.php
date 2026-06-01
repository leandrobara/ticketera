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
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Jobs\WhatsAppNotificationEvents\Traits\InjectLog;
use App\Services\API\LeadNotificationWhatsAppMessageService;
use App\Services\API\Dispatchers\WhatsAppNotificationEventsDispatcherService;
    

class SendWAPIGroupedLeadsNotificationMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;
    
    public User $user;
    public $timeout = 120;


    // $leadNotificationsWhatsAppIds ya viene agrupado por usuario.
    public function __construct(
        public readonly int $userId,
        public readonly array $leadNotificationsWhatsAppIds,
    ) {
    }


    public function handle()
    {
        $lockKey = 'SendWAPIGroupedLeadsNotificationMessageJob';


        //
        // FF. WAPI CANCELADO.
        //
        $notifsService = resolve(LeadNotificationWhatsAppMessageService::class);
        $groupedLeadNotifs = $notifsService->findByIds(new Collection($this->leadNotificationsWhatsAppIds));
        $notifsService->persistFailReason($groupedLeadNotifs, 'WAPI_STOPPED_BY_FF');
        return true;
        //



        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, $this->timeout);
        if (!$lockIsGranted) {
            dump('REQUEUED');
            $this->requeueThisJob();
            return true;
        }
        if (!$this->leadNotificationsWhatsAppIds) {
            dump('No leadNotificationsWhatsAppIds');
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            return true;
        }

        $this->user = resolve(UserService::class)->find($this->userId);
        if ($this->user?->wapi_is_paused) {
            $this->requeueThisJob(
                isPausedWapiRequeue: true,
                baseDelaySeconds: $this->user?->wapi_pause_delay_seconds ?? 300,
            );
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            return true;
        }

        try {
            $notifsService = resolve(LeadNotificationWhatsAppMessageService::class);
            $groupedLeadNotifs = $notifsService->findByIds(new Collection($this->leadNotificationsWhatsAppIds));
            
            $notifHasError = $this->handleAndPersistErrorIfExists($groupedLeadNotifs);
            if ($notifHasError) {
                resolve(LockHelper::class)->releaseLockByName($lockKey);
                return true;
            }

            $viewLeadsDataArr = [];
            foreach ($groupedLeadNotifs as $leadNotif) {
                if (!$leadNotif->lead) {
                    $notifsService->persistFailReason(new Collection([$leadNotif]), 'lead_was_deleted');
                    continue;
                }
                $encryptedLeadId = SimpleEncrypter::encryptInt($leadNotif->lead->id);
                $directLeadUrl = clientUrl($leadNotif->lead->client, "/?eli={$encryptedLeadId}");
                $viewLeadsDataArr[] = ['lead' => $leadNotif->lead, 'directLeadUrl' => $directLeadUrl];
            }
            if (!$viewLeadsDataArr) {
                resolve(LockHelper::class)->releaseLockByName($lockKey);
                return true;
            }

            // $user = $leadNotif->lead->user;
            $automationNewLead = $groupedLeadNotifs->first()->automationNewLead;
            $whatsAppMessageText = $automationNewLead->grouped_whatsapp_message_text;
            $viewRoute = 'api.whatsapp-message-notification.new-lead.grouped-notification-message';
            $viewData = ['leadsDataArr' => $viewLeadsDataArr, 'whatsAppMessageText' => $whatsAppMessageText];
            $chatMessageString = view($viewRoute, $viewData)->render();
            $chatMessageString = trim(preg_replace('/[ ]+/', ' ', $chatMessageString));
            $chatMessageString = str_ireplace(' *ID', '*ID', $chatMessageString); // hotfix espacio en blanco

            $WAPIHelperMessageDTO = WAPIHelperMessageDTO::build(
                chatMessage: $chatMessageString,
                phoneNumber: $this->user->wapi_session_phone_number,
                wapiSessionPhoneNumber: $this->user->wapi_session_phone_number,
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

            $WAPIResponse = resolve(WAPIHelper::class, ['user' => $this->user])->sendMessage($WAPIHelperMessageDTO);

            $notifsService->persistSuccessSent($groupedLeadNotifs);

            resolve(LockHelper::class)->releaseLockByName($lockKey);
        } catch (Exception $e) {
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            $notifsService->persistFailReason($groupedLeadNotifs, $e->getMessage());
            throw $e;
        }
    }


    // Devuelve true si hubo error
    protected function handleAndPersistErrorIfExists(Collection $groupedLeadNotifs): bool
    {
        $notifsService = resolve(LeadNotificationWhatsAppMessageService::class);

        if (!config('wapi.wapi_notifications_enabled')) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'wapi_notifications_is_not_enabled');
            return true;
        }

        // Exception para evitar envíos
        // $notifsService->persistFailReason($groupedLeadNotifs, '__WAP_NOTIFICATIONS_JOBS_MANUALLY_DISABLED__');
        // return true;

        if (!$this->user) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'user_was_deleted');
            return true;
        }
        if (!$this->user->enabled) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'user_is_not_enabled');
            return true;
        }
        // if (!$this->user->phone) {
        //     $notifsService->persistFailReason($groupedLeadNotifs, 'user_has_no_phone');
        //     return true;
        // }

        if ($groupedLeadNotifs->pluck('lead.user_id')->unique()->count() > 1) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'grouped_notifications_belong_to_multiple_users');
            return true;
        }
        if ($groupedLeadNotifs->first()->lead->user_id != $this->user->id) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'grouped_notifications_user_does_not_match');
            return true;
        }

        if ($groupedLeadNotifs->pluck('automation_new_lead_id')->unique()->count() > 1) {
            $notifsService->persistFailReason(
                $groupedLeadNotifs, 'grouped_notifications_belong_to_multiple_automations_new_lead'
            );
            return true;
        }
        
        $automationNewLead = $groupedLeadNotifs->first()->automationNewLead;
        if (!$automationNewLead) {
            $this->persistFailReason($groupedLeadNotifs, 'automation_new_lead_was_deleted');
            return true;
        }

        if (!$this->user->client->enabled) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'client_is_not_enabled');
            return true;
        }
        if (!$this->user->client->clientSettings->enable_new_lead_whatsapp_message_alert) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'disabled_new_lead_whatsapp_message_alert');
            return true;
        }

        if (!$this->user->client->clientSettings->enable_wapi) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'wapi_is_not_enabled');
            return true;
        }
        if (!$this->user->wapi_session_phone_number) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'user_is_not_synced_with_wapi');
            return true;
        }
        if (!$this->user->wapi_is_synced) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'user_is_not_synced_with_wapi');
            return true;
        }

        // Filtro de prevención
        $filteredNotificationsCount = $groupedLeadNotifs
            ->whereNull('sent_date')
            ->whereNull('exception')
            ->where('success', false)
            ->where('is_grouped', true)
            ->where('do_not_send', false)
            ->whereNotNull('dispatched_date')
            ->whereNotNull('automation_new_lead_id')
            ->count()
        ;
        if ($groupedLeadNotifs->count() != $filteredNotificationsCount) {
            $notifsService->persistFailReason($groupedLeadNotifs, 'grouped_lead_notification_matching_error');
            return true;
        }

        return false;
    }


    protected function requeueThisJob(int $baseDelaySeconds = 20, bool $isPausedWapiRequeue = false): void
    {
        $notifsService = resolve(LeadNotificationWhatsAppMessageService::class);
        $leadNotificationsWhatsApp = $notifsService->findByIds(new Collection($this->leadNotificationsWhatsAppIds));

        resolve(WhatsAppNotificationEventsDispatcherService::class)->dispatchSendWAPIGroupedLeadsNotificationMessageJob(
            $this->user, $leadNotificationsWhatsApp, $baseDelaySeconds
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
