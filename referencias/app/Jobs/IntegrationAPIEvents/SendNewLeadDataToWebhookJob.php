<?php

namespace App\Jobs\IntegrationAPIEvents;

use Throwable;
use Exception;
use App\Models\Lead;
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


class SendNewLeadDataToWebhookJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, InjectLog;

    public $leadId;
    public $webhookUrl;
    public $triggerCode;


    public function __construct(int $leadId, string $webhookUrl)
    {
        $this->leadId = $leadId;
        $this->webhookUrl = $webhookUrl;
        $this->triggerCode = ClientSettings::LEAD_CREATE_TRIGGER_WEBHOOK_CODE;
    }


    public function handle()
    {
        $urlKey = Str::after($this->webhookUrl, '//');
        $lockKey = "SendNewLeadDataToWebhookJob:{$urlKey}:handle:{$this->leadId}";
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, 3);
        if (!$lockIsGranted) {
            return null;
        }

        $lead = Lead::findOrFail($this->leadId);
        $clientSettings = $lead->client->clientSettings;
        if (!$clientSettings->enable_integration_api) {
            return null;
        }

        $webhookType = $clientSettings->findLeadCreateWebhookTypeByEndpoint($this->webhookUrl);
        if (!$webhookType) {
            throw new Exception('webhook_url_does_not_match');
        }

        resolve(IntegrationApiHelper::class)->sendLeadDataToEndpoint($lead, $this->triggerCode, $this->webhookUrl);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
