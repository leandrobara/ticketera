<?php

namespace App\Services\API\Dispatchers;

use Exception;
use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Services\Traits\CustomDispatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Jobs\SearchLeadEvents\UpdateLeadSearchInfoJob;
use App\Jobs\SearchLeadEvents\CheckSearchIndexedLeadsJob;
use App\Jobs\SearchLeadEvents\MultipleUpdateLeadSearchInfoJob;


class SearchLeadEventsDispatcherService
{

    use CustomDispatch;
    
    private $eventQueueName;
    private $queueConnection;

    // To avoid repeting elastic search index update multiple times on a same script run.
    private $updatedSearchInfoLeadIds = [];


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchCheckSearchIndexedLeadsJob(array $leadIds, int $delaySecs = 0)
    {
        if ($leadIds) {
            $this->doCustomDispatch(CheckSearchIndexedLeadsJob::class, [$leadIds], $delaySecs);
        }
    }


    public function dispatchUpdateLeadSearchInfoJob(int $leadId, int $delaySecs = 5)
    {
        if (!in_array($leadId, $this->updatedSearchInfoLeadIds)) {
            $this->doCustomDispatch(UpdateLeadSearchInfoJob::class, [$leadId], $delaySecs);
            $this->updatedSearchInfoLeadIds[] = $leadId;
        }
    }


    public function dispatchUpdateMultipleLeadSearchInfoJob(array $leadIds, int $delaySecs = 5)
    {
        $this->doCustomDispatch(MultipleUpdateLeadSearchInfoJob::class, [$leadIds], $delaySecs);
    }

}
