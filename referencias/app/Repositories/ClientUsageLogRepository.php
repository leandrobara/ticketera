<?php

namespace App\Repositories;

use DateTime;
use Exception;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Collection;
use App\Models\MongoDB\ClientUsageLog;
use Illuminate\Database\Eloquent\Builder;


class ClientUsageLogRepository implements Repository
{

    public function findAllByUser(User $user): Collection
    {
        return ClientUsageLog::where('user_id', $user->id)->get();
    }


    public function findVisitedScreenByUserBetweenDates(User $user, DateTime $dateStart, DateTime $dateEnd): Collection
    {
        return ClientUsageLog::query()
            ->where('user_id', $user->id)
            ->where('type', 'visitedScreen')
            ->where('created_at', '<=', $dateEnd)
            ->where('client_id', $user->client_id)
            ->where('created_at', '>=', $dateStart)
            ->get()
        ;
    }


    public function findVisitedScreenByClientBetweenDates(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd
    ): Collection {
        return ClientUsageLog::query()
            ->where('type', 'visitedScreen')
            ->where('client_id', $client->id)
            ->where('created_at', '<=', $dateEnd)
            ->where('created_at', '>=', $dateStart)
            ->get()
        ;
    }


    public function create(array $data): ClientUsageLog
    {
        if (!($data['data'] ?? null)) {
            throw new Exception('client_usage_log_data_field_is_missing');
        }
        if (!($data['user_id'] ?? null)) {
            throw new Exception('client_usage_log_user_id_field_is_missing');
        }
        if (!($data['client_id'] ?? null)) {
            throw new Exception('client_usage_log_client_id_field_is_missing');
        }
        $clientUsageLog = new ClientUsageLog($data);
        $saved = $clientUsageLog->save();
        if (!$saved) {
            throw new Exception('error_on_save');
        }
        return $clientUsageLog->fresh();
    }

}
