<?php

namespace App\Services\API;

use DateTime;
use Exception;
use DateTimeZone;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use App\Helpers\SimpleEncrypter;
use App\Services\API\LeadService;
use App\Models\AutomationNewLead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Models\LeadNotificationWhatsAppMessage;
use App\Services\Traits\StoresExistentInstance;
use App\Repositories\LeadNotificationWhatsAppMessageRepository;
use App\Services\API\Dispatchers\WhatsAppNotificationEventsDispatcherService;


class LeadNotificationWhatsAppMessageService
{

    use GetClientFromRequest, GetUserFromRequest, StoresExistentInstance;


    public function __construct(
        private readonly string $notificationsWhatsAppMessageEnabled,
        private readonly LeadNotificationWhatsAppMessageRepository $leadNotificationWhatsAppMessageRepository,
        private readonly WhatsAppNotificationEventsDispatcherService $whatsAppNotificationEventsDispatcherService,
    ) {
    }


    public function createNewDefault(Lead $lead): LeadNotificationWhatsAppMessage
    {
        $dateNow = new DateTime('now');
        $data = [
            'reason' => 'new_lead',
            'lead_id' => $lead->id,
            'user_id' => $lead->user->id,
            'client_id' => $lead->client->id,
            'send_date' => $dateNow->format('Y-m-d H:i:s'),
        ];

        $enabledNewLeadWhatsAppMsgNotification = $lead->client->clientSettings->enable_new_lead_whatsapp_message_alert;
        $disabled = !$this->notificationsWhatsAppMessageEnabled || !$enabledNewLeadWhatsAppMsgNotification;
        if ($disabled) {
            $data['send_date'] = null;
            $data['do_not_send'] = true;
        }

        $notif = $this->create($data);
        return $notif;
    }


    public function create(array $data): LeadNotificationWhatsAppMessage
    {
        $notificationsByAutomationNewLead = $this->leadNotificationWhatsAppMessageRepository->create($data);
        return $notificationsByAutomationNewLead;
    }


    public function update(LeadNotificationWhatsAppMessage $notification, array $data): LeadNotificationWhatsAppMessage
    {
        $notification = $this->leadNotificationWhatsAppMessageRepository->update($notification, $data);
        return $notification;
    }


    public function updateMultiple(Collection $notifications, array $data): bool
    {
        $updated = $this->leadNotificationWhatsAppMessageRepository->updateMultiple($notifications, $data);
        return $updated;
    }


    public function findByIds(Collection $ids): Collection
    {
        return $this->leadNotificationWhatsAppMessageRepository->findByIds($ids);
    }


    public function delete(LeadNotificationWhatsAppMessage $notificationsByAutomationNewLead): Note
    {
        $deleted = $this->leadNotificationWhatsAppMessageRepository->delete($notificationsByAutomationNewLead);
        return $deleted;
    }


    public function findGroupedToSend(Client $client): Collection
    {
        $notifs = $this->leadNotificationWhatsAppMessageRepository->findGroupedToSend($client);
        return $notifs;
    }


    public function markToDoNotSendByAutomationNewLead(
        LeadNotificationWhatsAppMessage $notif,
        AutomationNewLead $automation
    ): LeadNotificationWhatsAppMessage {
        $data = [
            'send_date' => null,
            'is_grouped' => false,
            'do_not_send' => true,
            'automation_new_lead_id' => $automation->id,
        ];
        $notif = $this->update($notif, $data);
        return $notif;
    }


    public function markToSendGroupedByAutomationNewLead(
        LeadNotificationWhatsAppMessage $notif,
        AutomationNewLead $automation
    ): LeadNotificationWhatsAppMessage {
        // Set send date at 18 hs. (18 hs. at Client's timezone!)
        $tz = $automation->client->timezone;
        $baseDate = (new DateTime('now'))->setTimezone(new DateTimeZone($tz));
        if (intval($baseDate->format('H')) >= 18) {
            $baseDate->modify('+1 day');
        }
        $sendDate = (clone($baseDate))->setTime(18, 0, 0);
        // Convert client's TZ to UTC0
        $sendDate->setTimezone(new DateTimeZone('UTC'));

        $data = [
            'is_grouped' => true,
            'do_not_send' => false,
            'send_date' => $sendDate,
            'automation_new_lead_id' => $automation->id,
        ];
        $notif = $this->update($notif, $data);
        return $notif;
    }


    //Se envia despues del create de API, antes de correr este metodo ya paso la automation.
    public function sendNewLeadWhatsAppMessageNotificationToLeadUser(Lead $lead): LeadNotificationWhatsAppMessage
    {
        $leadNotifWhatsAppMessage = $lead->leadNotificationWhatsAppMessage;
        if (!$leadNotifWhatsAppMessage) {
            throw new Exception('lead_lead_notification_whatsapp_message_does_not_exist');
        }

        if (!$this->notificationsWhatsAppMessageEnabled) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'disabled_notifications_whatsapp_message');
            return $leadNotifWhatsAppMessage->fresh();
        }
        
        if (!$lead->user) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'user_was_deleted');
            return $leadNotifWhatsAppMessage->fresh();
        }
        if (!$lead->user->enabled) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'user_is_not_enabled');
            return $leadNotifWhatsAppMessage->fresh();
        }
        if (!$lead->user->client->clientSettings->enable_new_lead_whatsapp_message_alert) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'disabled_new_lead_whatsapp_message_alert');
            return $leadNotifWhatsAppMessage->fresh();
        }

        if ($leadNotifWhatsAppMessage->do_not_send) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'lead_notification_has_do_not_send_flag');
            return $leadNotifWhatsAppMessage->fresh();
        }
        if ($leadNotifWhatsAppMessage->is_grouped) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'lead_notification_has_grouped_flag');
            return $leadNotifWhatsAppMessage->fresh();
        }
        if ($leadNotifWhatsAppMessage->dispatched_date) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'lead_notification_has_dispatched_date');
            return $leadNotifWhatsAppMessage->fresh();
        }
        if ($leadNotifWhatsAppMessage->sent_date) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'lead_notification_has_sent_date');
            return $leadNotifWhatsAppMessage->fresh();
        }
        if (!$leadNotifWhatsAppMessage->send_date) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'lead_notification_does_not_have_send_date');
            return $leadNotifWhatsAppMessage->fresh();
        }
        if ($leadNotifWhatsAppMessage->success) {
            $this->persistFailReason($leadNotifWhatsAppMessage, 'lead_notification_has_success_flag');
            return $leadNotifWhatsAppMessage->fresh();
        }

        try {
            DB::beginTransaction();
            
            $dateNow = new DateTime();
            $leadNotifWhatsAppMessage = $this->update($leadNotifWhatsAppMessage, ['dispatched_date' => $dateNow]);
            $this->whatsAppNotificationEventsDispatcherService->dispatchSendWAPILeadNotificationMessageJob(
                $leadNotifWhatsAppMessage
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $leadNotifWhatsAppMessage->fresh();
    }


    public function sendGroupedNewLeadNotificationWhatsAppMessageToLeadUsers(
        Collection $groupedNotifications
    ): Collection {
        if ($groupedNotifications->isEmpty()) {
            return new Collection();
        }
        if (!$this->notificationsWhatsAppMessageEnabled) {
            $this->persistFailReason($groupedNotifications, 'disabled_notifications_whatsapp_message');
            return new Collection();
        }

        // Filtro de prevención
        $groupedNotifications = $groupedNotifications
            ->whereNull('exception')
            ->whereNull('sent_date')
            ->where('is_grouped', true)
            ->where('do_not_send', false)
            ->whereNull('dispatched_date')
            ->whereNotNull('automation_new_lead_id')
        ;
        if ($groupedNotifications->isEmpty()) {
            $this->persistFailReason($groupedNotifications, 'grouped_lead_notification_matching_error');
            return new Collection();
        }
        // El cliente tiene deshabilitadas estas notificaciones
        if (!$groupedNotifications->first()->client->clientSettings->enable_new_lead_whatsapp_message_alert) {
            $this->persistFailReason($groupedNotifications, 'disabled_new_lead_whatsapp_message_alert');
            return new Collection();
        }

        $dateNow = new DateTime();
        $processedNotifications = new Collection();
        $notifsGroupedByAutomationNewLead = $groupedNotifications->groupBy('automation_new_lead_id');
        foreach ($notifsGroupedByAutomationNewLead as $autNewLeadNotifs) {
            $automationNewLead = $autNewLeadNotifs->first()->automationNewLead;
            if (!$automationNewLead) {
                $this->persistFailReason($autNewLeadNotifs, 'automation_new_lead_was_deleted');
                continue;
            }

            $notifsGroupedByUser = $autNewLeadNotifs->groupBy('lead.user_id');
            foreach ($notifsGroupedByUser as $userNotifs) {
                $user = $userNotifs->first()->lead->user;
                if (!$user) {
                    $this->persistFailReason($userNotifs, 'user_was_deleted');
                    continue;
                }
                if (!$user->enabled) {
                    $this->persistFailReason($userNotifs, 'user_is_not_enabled');
                    continue;
                }
                if (!$user->wapi_session_phone_number) {
                    $this->persistFailReason($userNotifs, 'user_is_not_synced_with_wapi');
                    continue;
                }
                if (!$user->wapi_is_synced) {
                    $this->persistFailReason($userNotifs, 'user_is_not_synced_with_wapi');
                    continue;
                }
                if (!$user->client->clientSettings->enable_wapi) {
                    $this->persistFailReason($userNotifs, 'wapi_is_not_enabled');
                    continue;
                }

                try {
                    DB::beginTransaction();
                    
                    $this->updateMultiple($userNotifs, ['dispatched_date' => $dateNow]);
                    $this->whatsAppNotificationEventsDispatcherService
                        ->dispatchSendWAPIGroupedLeadsNotificationMessageJob($user, $userNotifs)
                    ;

                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

                $refreshedNotifs = $this->findByIds($userNotifs->pluck('id'));
                $processedNotifications = $processedNotifications->merge($refreshedNotifs);
            }
        }
        return $processedNotifications;
    }


    public function persistFailReason(
        Collection  | LeadNotificationWhatsAppMessage $leadNotifications,
        string $failReason
    ): bool {
        if ($leadNotifications instanceof LeadNotificationWhatsAppMessage) {
            $leadNotifications = new Collection([$leadNotifications]);
        }
        $updateData = ['success' => false, 'exception' => $failReason];
        $updated = $this->updateMultiple($leadNotifications, $updateData);
        return $updated;
    }


    public function persistSuccessSent(Collection | LeadNotificationWhatsAppMessage $leadNotifications): bool
    {
        if ($leadNotifications instanceof LeadNotificationWhatsAppMessage) {
            $leadNotifications = new Collection([$leadNotifications]);
        }
        $updateData = ['success' => true, 'sent_date' => new DateTime()];
        $updated = $this->updateMultiple($leadNotifications, $updateData);
        return $updated;
    }

}
