<?php

namespace App\Repositories\WapBot;

use App\Models\WapBot;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Repositories\Traits\VoidClearCache;


class WapBotRepository implements Repository
{

    use VoidClearCache;


    public function find(int $wapBotId, array $opts = []): ?WapBot
    {
        $query = WapBot::query();
        if (!empty($opts['withTrashed'])) {
            $query->withTrashed();
        }
        return $query->find($wapBotId);
    }


    public function findById(int $wapBotId): ?WapBot
    {
        return WapBot::find($wapBotId);
    }


    public function findActiveByMetaPhoneNumberId(string $metaPhoneNumberId): ?WapBot
    {
        return WapBot::where('meta_phone_number_id', $metaPhoneNumberId)
            ->where('enabled', true)
            ->first()
        ;
    }
    

    public function findActive(int $clientId, string $metaPhoneNumberId): ?WapBot
    {
        return WapBot::where('meta_phone_number_id', $metaPhoneNumberId)
            ->where('enabled', true)
            ->where('client_id', $clientId)
            ->orderByDesc('id')
            ->first()
        ;
    }


    public function findEnabledWithFollowUp(): Collection
    {
        return WapBot::where('enabled', true)
            ->whereNotNull('followup_1_message')
            ->whereNotNull('followup_1_delay_minutes')
            ->whereHas('client', fn($q) => $q->where('enabled', true))
            ->whereHas('whatsAppMetaAPIConnection')
            ->with(['client'])
            ->orderBy('id')
            ->get()
        ;
    }


    public function findEnabledWithEnabledLeadsAutoCreation(): Collection
    {
        return WapBot::where('enabled', true)
            ->whereNotNull('auto_create_lead_after_minutes')
            ->whereHas('client', fn($q) => $q->where('enabled', true))
            ->whereHas('whatsAppMetaAPIConnection')
            ->with(['client'])
            ->orderBy('id')
            ->get()
        ;
    }


    public function create(array $data): WapBot
    {
        return WapBot::create($data);
    }


    public function update(WapBot $wapBot, array $data): WapBot
    {
        $wapBot->update($data);
        return $wapBot->fresh();
    }


    public function delete(WapBot $wapBot): WapBot
    {
        $wapBot->delete();
        return $wapBot->fresh();
    }

}
