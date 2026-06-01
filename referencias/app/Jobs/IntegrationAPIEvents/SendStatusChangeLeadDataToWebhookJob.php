<?php

namespace App\Jobs\IntegrationAPIEvents;

use Throwable;
use Exception;
use App\Models\Lead;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Models\ClientSettings;
use Illuminate\Support\Facades\Log;
use App\Helpers\IntegrationApiHelper;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Overrides\Dispatchers\CustomDispatchable;


class SendStatusChangeLeadDataToWebhookJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    public $leadId;
    public $webhookUrl;
    public $triggerCode;
    public $logUuid = null;


    public function __construct(int $leadId, string $webhookUrl)
    {
        $this->leadId = $leadId;
        $this->webhookUrl = $webhookUrl;
        $this->triggerCode = ClientSettings::LEAD_STATUS_CHANGE_TRIGGER_WEBHOOK_CODE;
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        
        $this->logInfo("Starting SendStatusChangeLeadDataToWebhookJob");
        $this->logInfo("leadId: {$this->leadId}");
        $this->logInfo("webhookUrl: {$this->webhookUrl}");
        $this->logInfo("triggerCode: {$this->triggerCode}");
    
        if (environmentIsNotProduction()) {
            $this->logInfo("SendStatusChangeLeadDataToWebhookJob leadId: {$this->leadId}");
            $this->logInfo('ENDING JOB - Env. IS NOT PROD' . PHP_EOL . PHP_EOL);
            return null;
        }
        
        if (!$this->webhookUrl) {
            $this->logInfo('webhookUrl is empty. RETURNING');
            return null;
        }

        $urlKey = Str::after($this->webhookUrl, '//');
        $lockKey = "SendStatusChangeLeadDataToWebhookJob:{$urlKey}:handle:{$this->leadId}";
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, 3);
        if (!$lockIsGranted) {
            $this->logInfo('Lock not granted. RETURNING');
            return null;
        }

        $lead = Lead::findOrFail($this->leadId);
        $clientSettings = $lead->client->clientSettings;

        $this->logInfo("clientId: {$lead->client->id}");
        $this->logInfo("clientSettingsId: {$clientSettings->id}");
        $this->logInfo("clientSettings.enable_integration_api: {$clientSettings->enable_integration_api}");
        
        if (!$clientSettings->enable_integration_api) {
            $this->logInfo('clientSettings.enable_integration_api is false. RETURNING');
            return null;
        }

        $webhookType = $clientSettings->findLeadStatusChangeWebhookTypeByEndpoint($this->webhookUrl);
        if (!$webhookType) {
            throw new Exception('webhook_url_does_not_match');
        }

        $this->logInfo('Sending lead data');
        resolve(IntegrationApiHelper::class)->sendLeadDataToEndpoint($lead, $this->triggerCode, $this->webhookUrl);
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('SendStatusChangeLeadDataToWebhookJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('SendStatusChangeLeadDataToWebhookJobInfo');
    }

}
