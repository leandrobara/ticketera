<?php

namespace App\Services\API\Dispatchers;

use App\Models\User;
use App\Models\Lead;
use App\Models\Status;
use App\Models\Client;
use App\Models\LeadContactEmail;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\CustomDispatch;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Jobs\GoogleContactsEvents\SyncNewLeadWithGoogleContactsJob;
use App\Jobs\GoogleContactsEvents\SyncUpdatedLeadWithGoogleContactsJob;
use App\Jobs\GoogleContactsEvents\SyncChangedUserLeadWithGoogleContactsJob;


class GoogleContactsEventsDispatcherService
{

    use CustomDispatch;

    private $eventQueueName;
    private $queueConnection;


    // Para evitar disparar varias veces si se actualizan varios phones, emails del lead al mismo tiempo.
    private $updatedLeadIds = [];


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchSyncNewLeadWithGoogleContactsJob(Lead $lead, int $delaySecs = 5)
    {
        $this->doCustomDispatch(
            SyncNewLeadWithGoogleContactsJob::class,
            [$lead->id, null],
            $delaySecs,
            $lead->client_id
        );
    }


    public function dispatchSyncUpdatedLeadWithGoogleContactsJob(Lead $lead, int $delaySecs = 5)
    {
        if (!in_array($lead->id, $this->updatedLeadIds)) {
            $clientId = $lead->client_id;
            $this->doCustomDispatch(SyncUpdatedLeadWithGoogleContactsJob::class, [$lead->id], $delaySecs, $clientId);
            $this->updatedLeadIds[] = $lead->id;
        }
    }


    public function dispatchSyncMultipleLeadsJobs(
        Client $client,
        User $loginUser,
        Collection $leadIds,
        int $delaySecs = 5
    ) {
        foreach ($leadIds as $leadId) {
            if (!in_array($leadId, $this->updatedLeadIds)) {
                $delaySecs = $delaySecs + 2;
                $this->doCustomDispatch(
                    SyncNewLeadWithGoogleContactsJob::class,
                    [$leadId, $loginUser->id],
                    $delaySecs,
                    $client->id
                );
                $this->updatedLeadIds[] = $leadId;
            }
        }
    }


    public function dispatchSyncChangedUserLeadWithGoogleContactsJob(Lead $lead, int $oldUserId, int $delaySecs = 5)
    {
        $params = [$lead->id, $oldUserId];
        $this->doCustomDispatch(
            SyncChangedUserLeadWithGoogleContactsJob::class, $params, $delaySecs, $lead->client_id
        );
    }

}
