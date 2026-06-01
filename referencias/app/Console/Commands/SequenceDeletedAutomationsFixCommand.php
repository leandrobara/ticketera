<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\AutomationEmailSend;
use App\Models\WAutomationSequence;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\Automations\AutomationEmailSendService;
use App\Services\API\WAutomations\WAutomationSequenceService;


class SequenceDeletedAutomationsFixCommand extends Command
{

    protected $signature = 'sequence-deleted-automations:fix-deleted';
    protected $description = 'Fix deleted automations and wautomations sequences with non deleted steps';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clients = Client::all();
        $automationEmailSendService = resolve(AutomationEmailSendService::class);
        $wAutomationSequenceService = resolve(WAutomationSequenceService::class);

        $deletedAutomations = AutomationEmailSend::withTrashed()->whereNotNull('deleted_at')->get();
        foreach ($deletedAutomations as $automationEmailSend) {
            foreach ($automationEmailSend->automationEmailSendSteps as $step) {
                $step->delete();
                $this->info("- DELETED automationEmailSendStep ID: {$step->id}");
            }
        }

        $deletedWAutomations = WAutomationSequence::withTrashed()->whereNotNull('deleted_at')->get();
        foreach ($deletedWAutomations as $wAutomationSequence) {
            foreach ($wAutomationSequence->wAutomationSequenceSteps as $step) {
                $step->delete();
                $this->info("- DELETED WAutomationSequenceStep ID: {$step->id}");
            }
        }
    }

}
