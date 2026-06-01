<?php

namespace App\Services\API\Dispatchers;

use Exception;
use App\Models\Lead;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\CustomDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Jobs\ElasticLeadEvents\CheckElasticIndexedLeadsJob;


// @DEPRECATED
class ElasticLeadEventsDispatcherService
{

    use CustomDispatch;
    
    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchCheckElasticIndexedLeadsJob(array $leadIds, int $delaySecs = 0)
    {
        if ($leadIds) {
            $this->doCustomDispatch(CheckElasticIndexedLeadsJob::class, [$leadIds], $delaySecs);
        }
    }

}
