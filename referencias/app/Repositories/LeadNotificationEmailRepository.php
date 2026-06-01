<?php

namespace App\Repositories;

use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\LeadNotificationEmail;


class LeadNotificationEmailRepository
{

    public function findOrFail(int $id): LeadNotificationEmail
    {
        return LeadNotificationEmail::findOrFail($id);
    }


    public function findGroupedToSend(): Collection
    {
        $dateNow = new DateTime('now');
        $notifs = LeadNotificationEmail::where('is_grouped', true)
            ->where('do_not_send', false)
            ->whereNull('sent_date')
            ->whereNull('scheduled_date')
            ->whereNotNull('automation_new_lead_id')
            ->where('send_date', '<=', $dateNow->format('Y-m-d H:i:s'))
            ->with(['automationNewLead', 'client', 'lead'])
            ->get()
        ;

        return $notifs;
    }


    public function create(array $data): LeadNotificationEmail
    {
        $notif = new LeadNotificationEmail($data);
        $notif->saveOrFail();
        return $notif->fresh();
    }


    public function update(LeadNotificationEmail $notif, array $data): LeadNotificationEmail
    {
        $notif->fill($data);
        $notif->saveOrFail();
        return $notif->fresh();
    }


    public function updateMultiple(Collection $leadNotificationEmails, array $data): Collection
    {
        $ids = $leadNotificationEmails->pluck('id');
        $updated = LeadNotificationEmail::whereIn('id', $ids)->update($data);
        $updatedNotifs = LeadNotificationEmail::whereIn('id', $ids)->get();
        return $updatedNotifs;
    }


    public function delete(LeadNotificationEmail $notif): LeadNotificationEmail
    {
        $notif->delete();
        return $notif->fresh();
    }

}
