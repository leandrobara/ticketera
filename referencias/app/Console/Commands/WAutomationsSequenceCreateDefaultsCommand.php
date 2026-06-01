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
use App\Services\API\WAutomations\WAutomationSequenceService;


class WAutomationsSequenceCreateDefaultsCommand extends Command
{

    protected $description = 'Create default WAutomationsSequence for all clients';
    protected $signature = 'wautomations-sequence:create-defaults {--chunk=} {--offset=}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clients = Client::all();
        $wAutomationSequenceService = resolve(WAutomationSequenceService::class);
        
        foreach ($clients as $client) {
            $wAutomations = $wAutomationSequenceService->findByClient($client);
            $afterSaleExists = $wAutomations->where('trigger_type', 'after_sale')->isNotEmpty();
            $afterSentProposalExists = $wAutomations->where('trigger_type', 'after_sent_proposal')->isNotEmpty();

            if ($afterSaleExists || $afterSentProposalExists) {
                $this->info("\n-----------------------------------");
                $this->info("- Client ID: {$client->id}");
                if ($afterSaleExists) {
                    $this->info("- EXISTENT AFTER SALE");
                }
                if ($afterSentProposalExists) {
                    $this->info("- EXISTENT AFTER SENT PROPOSAL");
                }
                $this->info("-----------------------------------\n");
                continue;
            }
            

            try {
                DB::beginTransaction();
                if (!$afterSaleExists) {
                    $wAutAfterSale = $wAutomationSequenceService->createNewClientDefaultAfterSale($client);
                }
                if (!$afterSentProposalExists) {
                    $wAutAfterSentProp = $wAutomationSequenceService->createNewClientDefaultAfterSentProposal($client);
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $this->info("\n-----------------------------------");
            $this->info("- CREATED NEW  ");
            $this->info("- Client ID: {$client->id}");
            if ($wAutAfterSale ?? false) {
                $this->info("- WAutomationSequence ID: {$wAutAfterSale->id}");
            }
            if ($wAutAfterSentProp ?? false) {
                $this->info("- WAutomationSequence ID: {$wAutAfterSentProp->id}");
            }
            $this->info("-----------------------------------\n");
        }
    }

}
