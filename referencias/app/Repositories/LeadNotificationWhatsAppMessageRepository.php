<?php

namespace App\Repositories;

use DateTime;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\LeadNotificationWhatsAppMessage;

class LeadNotificationWhatsAppMessageRepository
{

    public function create(array $data): LeadNotificationWhatsAppMessage
    {
        $notif = new LeadNotificationWhatsAppMessage($data);
        $notif->saveOrFail();
        return $notif->fresh();
    }


    public function update(LeadNotificationWhatsAppMessage $notif, array $data): LeadNotificationWhatsAppMessage
    {
        $notif->fill($data);
        $notif->saveOrFail();
        return $notif->fresh();
    }


    public function updateMultiple(Collection $notifications, array $data): bool
    {
        $ids = $notifications->pluck('id');
        $updated = LeadNotificationWhatsAppMessage::whereIn('id', $ids)->update($data);
        return $updated;
    }


    public function findByIds(Collection $ids): Collection
    {
        return LeadNotificationWhatsAppMessage::whereIn('id', $ids)->get();
    }


    public function delete(LeadNotificationWhatsAppMessage $notif): LeadNotificationWhatsAppMessage
    {
        $notif->delete();
        return $notif->fresh();
    }


    public function findGroupedToSend(Client $client): Collection
    {
        $dateNow = new DateTime();
        $notifs = LeadNotificationWhatsAppMessage::where('is_grouped', true)
            ->whereNull('exception')
            ->whereNull('sent_date')
            ->where('success', false)
            ->where('do_not_send', false)
            ->whereNull('dispatched_date')
            ->where('client_id', $client->id)
            ->where('send_date', '<=', $dateNow)
            ->whereNotNull('automation_new_lead_id')
            ->with(['automationNewLead', 'client', 'lead'])
            ->get()
        ;
        return $notifs;
    }
    
}
