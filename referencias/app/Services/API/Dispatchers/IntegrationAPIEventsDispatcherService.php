<?php

namespace App\Services\API\Dispatchers;

use Exception;
use App\Models\Lead;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\CustomDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Overrides\Dispatchers\CustomPendingDispatch;
use App\Jobs\IntegrationAPIEvents\SendNewLeadDataToWebhookJob;
use App\Jobs\IntegrationAPIEvents\SendNewTaskDataToWebhookJob;
use App\Jobs\IntegrationAPIEvents\SendNewLeadSaleDataToWebhookJob;
use App\Jobs\IntegrationAPIEvents\SendStatusChangeLeadDataToWebhookJob;


class IntegrationAPIEventsDispatcherService
{

    use CustomDispatch;
    
    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchSendNewLeadDataToWebhookJob(Lead $lead, string $webhookUrl)
    {
        // Delay some seconds, so automations and other things can run first.
        $params = [$lead->id, $webhookUrl];
        $this->doCustomDispatch(SendNewLeadDataToWebhookJob::class, $params, 20, $lead->client_id);
    }


    public function dispatchSendNewLeadSaleDataToWebhookJob(Lead $lead, string $webhookUrl)
    {
        // Delay some seconds, so automations and other things can run first.
        $params = [$lead->id, $webhookUrl];
        $this->doCustomDispatch(SendNewLeadSaleDataToWebhookJob::class, $params, 20, $lead->client_id);
    }

    
    public function dispatchSendStatusChangeLeadDataToWebhookJob(Lead $lead, string $webhookUrl)
    {
        // Delay some seconds, so automations and other things can run first.
        $params = [$lead->id, $webhookUrl];
        $this->doCustomDispatch(SendStatusChangeLeadDataToWebhookJob::class, $params, 20, $lead->client_id);
    }


    public function dispatchSendNewTaskDataToWebhookJob(Task $task, string $webhookUrl)
    {
        // Delay some seconds, so automations and other things can run first.
        $params = [$task->id, $webhookUrl];
        $this->doCustomDispatch(SendNewTaskDataToWebhookJob::class, $params, 20, $task->client_id);
    }

}
