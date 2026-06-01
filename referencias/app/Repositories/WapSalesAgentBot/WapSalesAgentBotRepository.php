<?php

namespace App\Repositories\WapSalesAgentBot;

use App\Repositories\Repository;
use App\Models\WapSalesAgentBot;
use App\Repositories\Traits\VoidClearCache;


class WapSalesAgentBotRepository implements Repository
{

    use VoidClearCache;


    public function find(int $id, array $opts = []): ?WapSalesAgentBot
    {
        $query = WapSalesAgentBot::query();
        if (!empty($opts['withTrashed'])) {
            $query->withTrashed();
        }
        return $query->find($id);
    }


    public function findActive(int $clientId, string $metaPhoneNumberId): ?WapSalesAgentBot
    {
        return WapSalesAgentBot::where('meta_phone_number_id', $metaPhoneNumberId)
            ->where('is_enabled', true)
            ->where('client_id', $clientId)
            ->orderByDesc('id')
            ->first()
        ;
    }


    public function findActiveByMetaPhoneNumberId(string $metaPhoneNumberId): ?WapSalesAgentBot
    {
        return WapSalesAgentBot::where('meta_phone_number_id', $metaPhoneNumberId)
            ->where('is_enabled', true)
            ->first()
        ;
    }


    public function create(array $data): WapSalesAgentBot
    {
        return WapSalesAgentBot::create($data);
    }


    public function update(WapSalesAgentBot $bot, array $data): WapSalesAgentBot
    {
        $bot->update($data);
        return $bot->fresh();
    }


    public function delete(WapSalesAgentBot $bot): WapSalesAgentBot
    {
        $bot->delete();
        return $bot->fresh();
    }

}
