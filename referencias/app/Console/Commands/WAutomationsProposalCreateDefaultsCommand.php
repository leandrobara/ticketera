<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\WAutomationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\WAutomations\WAutomationProposalService;


class WAutomationsProposalCreateDefaultsCommand extends Command
{

    protected $description = 'Create default WAutomationsProposal for all clients';
    protected $signature = 'wautomations-proposal:create-defaults {--chunk=} {--offset=}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clients = Client::all();
        $wAutomationProposalService = resolve(WAutomationProposalService::class);
        
        foreach ($clients as $client) {
            $existent = $wAutomationProposalService->findWAutomationProposalByClient($client);
            if ($existent) {
                $this->info("\n-----------------------------------");
                $this->info("- EXISTENT ");
                $this->info("- Client ID: {$client->id}");
                $this->info("-----------------------------------\n");
                continue;
            }

            try {
                DB::beginTransaction();
                $createdWAutomation = $wAutomationProposalService->createNewClientDefault($client);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $this->info("\n-----------------------------------");
            $this->info("- CREATED NEW  ");
            $this->info("- Client ID: {$client->id}");
            $this->info("- WAutomationProposal ID: {$createdWAutomation->id}");
            $this->info("-----------------------------------\n");
        }
    }

}
