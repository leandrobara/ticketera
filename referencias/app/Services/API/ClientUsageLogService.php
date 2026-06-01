<?php

namespace App\Services\API;

use DateTime;
use Exception;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Collection;
use App\Models\MongoDB\ClientUsageLog;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\ClientUsageLogRepository;


class ClientUsageLogService
{

    public function __construct(
        protected readonly ClientUsageLogRepository $clientUsageLogRepository,
    ) {
    }

    
    public function findVisitedScreenByUserBetweenDates(User $user, DateTime $dateStart, DateTime $dateEnd): Collection
    {
        $clientUsageLogs = $this->clientUsageLogRepository->findVisitedScreenByUserBetweenDates(
            $user, $dateStart, $dateEnd
        );
        return $clientUsageLogs;
    }
    
    public function findVisitedScreenByClientBetweenDates(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd
    ): Collection {
        $clientUsageLogs = $this->clientUsageLogRepository->findVisitedScreenByClientBetweenDates(
            $client, $dateStart, $dateEnd
        );
        return $clientUsageLogs;
    }


    public function storeVisitedScreenUrl(int $clientId, int $userId, string $visitedScreenUrl): ClientUsageLog
    {
        $logData = [
            'url' => $visitedScreenUrl,
            'category' => $this->getScreenUrlCategory($visitedScreenUrl),
        ];
        $clientUsageLogData = [
            'data' => $logData,
            'user_id' => $userId,
            'client_id' => $userId,
            'type' => 'visitedScreen',
        ];
        $clientUsageLog = $this->clientUsageLogRepository->create($clientUsageLogData);
        return $clientUsageLog;
    }


    // public function create(array $data, ?User $user): ClientUsageLog
    // {
    //     $user = $user ?? $this->getUser();
    //     $clientUsageLogData = ['data' => $data, 'client_id' => $user->client_id, 'user_id' => $user->id];
    //     $clientUsageLog = $this->clientUsageLogRepository->create($clientUsageLogData);
    //     return $clientUsageLog;
    // }


    protected function getScreenUrlCategory(string $screenUrl): string
    {
        $map = [
            'reports/sales-history' => 'reports',
            'reports/tag-close-rate' => 'reports',
            'reports/user-close-rate' => 'reports',
            'reports/utm-adgroup-trace' => 'reports',
            'reports/utm-content-trace' => 'reports',
            'reports/utm-keywords-trace' => 'reports',
            'reports/utm-campaign-trace' => 'reports',
            'reports/acquisition-channels' => 'reports',
            'reports/sent-proposal-history' => 'reports',

            'configurations/tags' => 'configurations',
            'configurations/users' => 'configurations',
            'configurations/status' => 'configurations',
            'configurations/sync-wapi' => 'configurations',
            'configurations/tag-categories' => 'configurations',
            'configurations/client-settings' => 'configurations',
            'configurations/user-email-sign' => 'configurations',
            'configurations/sync-google-gmail' => 'configurations',
            'configurations/sync-email-address' => 'configurations',
            'configurations/leads-custom-fields' => 'configurations',
            'configurations/sync-facebook-pages' => 'configurations',
            'configurations/sync-google-contacts' => 'configurations',
            'configurations/acquisition-channels' => 'configurations',

            'configurations/task-templates' => 'templates',
            'configurations/email-templates' => 'templates',
            'configurations/whatsapp-templates' => 'templates',
        ];

        return $map[$screenUrl] ?? 'other';
    }

}
