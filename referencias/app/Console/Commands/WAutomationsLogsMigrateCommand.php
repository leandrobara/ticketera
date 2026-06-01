<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Models\AutomationLog;
use App\Models\WAutomationLog;
use Illuminate\Console\Command;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Support\Facades\Artisan;


class WAutomationsLogsMigrateCommand extends Command
{

    protected $signature = 'wautomations-logs:migrate {--chunk=} {--offset=}';
    protected $description = 'Migrate from AutomationsLogs to WAutomationsLogs';


    public function __construct()
    {
        parent::__construct();
    }


    // Multiple docs version
    public function handle()
    {
        $chunk = (int) ($this->option('chunk') ?? 500);

        $queryBuilder = AutomationLog::whereNotNull('automation_whatsapp_sending_id')->orderBy('id');
        $queryBuilder->chunk($chunk, function ($automationLogs) {
            foreach ($automationLogs as $automationLog) {
                $wapSendingMsg = WhatsAppSendingMessage::withTrashed()->findOrFail(
                    $automationLog->whatsapp_sending_message_id
                );

                $data = [
                    'lead_id' => $automationLog->lead_id,
                    'exception' => $automationLog->exception,
                    'client_id' => $automationLog->client_id,
                    'whatsapp_sending_message_id' => $wapSendingMsg->id,
                    'is_fully_applied' => $automationLog->is_fully_applied,
                    'whatsapp_sending_id' => $wapSendingMsg->whatsAppSending->id,
                    'wautomation_after_send_id' => $automationLog->automation_whatsapp_sending_id,
                    'created_at' => $automationLog->created_at,
                    'updated_at' => $automationLog->updated_at,
                ];
                $wAutomationLog = new WAutomationLog($data);
                $existentWAutomationLog = WAutomationLog::where('whatsapp_sending_message_id', $wapSendingMsg->id)
                    ->where('wautomation_after_send_id', $automationLog->automation_whatsapp_sending_id)
                    ->first()
                ;

                if ($existentWAutomationLog) {
                    $this->info("\n-----------------------------------");
                    $this->info("- EXISTENT ");
                    $this->info("- AutomationLog ID: {$automationLog->id}");
                    $this->info("- WAP Sending ID: {$wapSendingMsg->whatsAppSending->id}");
                    $this->info("- WAP Sending MSG ID: {$wapSendingMsg->id}");
                    $this->info("-----------------------------------\n");
                } else {
                    $wAutomationLog->saveOrFail();
                    $wAutomationLog = $wAutomationLog->fresh();
                    $this->info("\n-----------------------------------");
                    $this->info("- SAVED ");
                    $this->info("- AutomationLog ID: {$automationLog->id}");
                    $this->info("- WAutomationLog ID: {$wAutomationLog->id}");
                    $this->info("- WAP Sending ID: {$wapSendingMsg->whatsAppSending->id}");
                    $this->info("- WAP Sending MSG ID: {$wapSendingMsg->id}");
                    $this->info("-----------------------------------\n");
                }
            }
        });
    }

}
