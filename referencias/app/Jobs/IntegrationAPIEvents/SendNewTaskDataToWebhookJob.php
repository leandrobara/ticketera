<?php

namespace App\Jobs\IntegrationAPIEvents;

use Throwable;
use Exception;
use App\Models\Task;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Models\ClientSettings;
use App\Helpers\IntegrationApiHelper;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Jobs\IntegrationAPIEvents\Traits\InjectLog;
use App\Http\Resources\Integration\WebhookTaskResource;


class SendNewTaskDataToWebhookJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, InjectLog;

    public $taskId;
    public $webhookUrl;
    public $triggerCode;


    public function __construct(int $taskId, string $webhookUrl)
    {
        $this->taskId = $taskId;
        $this->webhookUrl = $webhookUrl;
        $this->triggerCode = ClientSettings::TASK_CREATE_TRIGGER_WEBHOOK_CODE;
    }


    public function handle()
    {
        $urlKey = Str::after($this->webhookUrl, '//');
        $lockKey = "SendNewTaskDataToWebhookJob:{$urlKey}:handle:{$this->taskId}";
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, 3);
        if (!$lockIsGranted) {
            return null;
        }

        $task = Task::findOrFail($this->taskId);
        $clientSettings = $task->client->clientSettings;
        if (!$clientSettings->enable_integration_api) {
            return null;
        }

        $webhookType = $clientSettings->findTaskCreateWebhookTypeByEndpoint($this->webhookUrl);
        if (!$webhookType) {
            throw new Exception('webhook_url_does_not_match');
        }

        resolve(IntegrationApiHelper::class)->sendTaskDataToEndpoint($task, $this->triggerCode, $this->webhookUrl);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
