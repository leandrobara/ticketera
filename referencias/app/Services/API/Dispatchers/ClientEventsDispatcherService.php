<?php

namespace App\Services\API\Dispatchers;

use App\Models\User;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\DTO\Import\LeadsClientDTO;
use App\Services\Traits\CustomDispatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Jobs\ClientEvents\ClearClientCacheJob;
use App\Jobs\ClientEvents\SaveClientInteractionJob;
use App\Jobs\ClientEvents\SaveVisitedScreenUrlUsageLogJob;
use App\Jobs\ClientEvents\SendDeletedLeadsNotificationJob;
use App\Jobs\ClientEvents\SendExportedLeadsNotificationJob;
use App\Jobs\ClientEvents\CreateAllClientsUsageReportFileJob;
use App\Jobs\ClientEvents\CreateClientEmailTemplatesByBusinessAreaJob;
use App\Jobs\ClientEvents\CreateClientWhatsAppTemplatesByBusinessAreaJob;


class ClientEventsDispatcherService
{

    use CustomDispatch;

    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchClearClientCacheJob(Client $client)
    {
        $this->doCustomDispatch(ClearClientCacheJob::class, [$client->id], null, $client->id);
    }


    public function dispatchCreateClientEmailTemplatesByBusinessAreaJob(
        Client $client,
        LeadsClientDTO $leadsClientDTO
    ): void {
        $businessAreaName = $leadsClientDTO->businessArea['name'] ?? null;
        $businessAreaChildName = $leadsClientDTO->businessAreaChild['name'] ?? null;
        if (!$businessAreaName) {
            return;
        }

        $params = [$client->id, $businessAreaName, $businessAreaChildName];
        $this->doCustomDispatch(CreateClientEmailTemplatesByBusinessAreaJob::class, $params, null, $client->id);
    }


    public function dispatchCreateClientWhatsAppTemplatesByBusinessAreaJob(
        Client $client,
        LeadsClientDTO $leadsClientDTO
    ): void {
        $businessAreaName = $leadsClientDTO->businessArea['name'] ?? null;
        $businessAreaChildName = $leadsClientDTO->businessAreaChild['name'] ?? null;
        if (!$businessAreaName) {
            return;
        }

        $params = [$client->id, $businessAreaName, $businessAreaChildName];
        $this->doCustomDispatch(CreateClientWhatsAppTemplatesByBusinessAreaJob::class, $params, null, $client->id);
    }


    public function dispatchMultipleClearClientCacheJob(Collection $clientIds)
    {
        foreach ($clientIds as $clientId) {
            $this->doCustomDispatch(ClearClientCacheJob::class, [$clientId], null, $clientId);
        }
    }


    public function dispatchSendExportedLeadsNotificationJob(
        Client $client,
        User $user,
        ?string $userIp,
        array $exportRawFilters,
        int $exportedLeadsCount,
    ) {
        $params = [$user->id, $userIp, $exportRawFilters, $exportedLeadsCount];
        $this->doCustomDispatch(SendExportedLeadsNotificationJob::class, $params, null, $client->id);
    }


    public function dispatchSendDeletedLeadsNotificationJob(
        Client $client,
        User $user,
        ?string $userIp,
        int $deletedLeadsCount,
    ) {
        $params = [$user->id, $userIp, $deletedLeadsCount];
        $this->doCustomDispatch(SendDeletedLeadsNotificationJob::class, $params, null, $client->id);
    }


    public function dispatchSaveClientInteractionJob(?Client $client)
    {
        if ($client) {
            $this->doCustomDispatch(SaveClientInteractionJob::class, [$client->id], null, $client->id);
        }
    }


    // Deprecado, no se usa más (17 Jul 2023)
    public function dispatchCreateAllClientsUsageReportFileJob()
    {
        // $this->doCustomDispatch(CreateAllClientsUsageReportFileJob::class, [], null, 2);
    }


    public function dispatchSaveVisitedScreenUrlUsageLogJob(Request $webReq)
    {
        $userId = $webReq->user->id;
        $visitedScreenUrl = $webReq->path();
        $clientId = $webReq->user->client_id;
        $jobData = [$clientId, $userId, $visitedScreenUrl];
        $this->doCustomDispatch(SaveVisitedScreenUrlUsageLogJob::class, $jobData, null, $clientId);
    }

}
