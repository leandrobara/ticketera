<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;
use App\Helpers\IntegrationApiHelper;
use Illuminate\Support\Facades\Artisan;
use App\Http\Resources\Integration\WebhookLeadResource;


class IntegrationAPIWebhookSendTestCommand extends Command
{

    protected $signature = 'integration-api:webhook-send-test {--lead-id=} {--endpoint=}';
    protected $description = 'Send a webhook test request using Clienty Integration API';


    public function __construct()
    {
        parent::__construct();
    }


    // Multiple docs version
    public function handle()
    {
        $leadId = (int) $this->option('lead-id');
        $webhookEndpoint = (string) $this->option('endpoint');

        $lead = Lead::findOrFail($leadId);
        $apiHelper = resolve(IntegrationApiHelper::class);
        $response = $apiHelper->sendLeadDataToEndpoint($lead, 'testTriggerCode', $webhookEndpoint);
        $this->info('-----------------');
        $this->info('- Response:');
        $this->info('-----------------');
        $this->info($response);
    }

}
