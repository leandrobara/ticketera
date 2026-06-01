<?php

namespace App\Console\Commands;

use DateTime;
use Exception;
use Illuminate\Console\Command;
use App\Models\LeadContactEmail;
use App\Helpers\MondayAPIHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use App\Models\MongoDB\MondayChurnBoardClient;
use App\DTO\Monday\MondayAPIChurnBoardClientDTO;
use App\Services\API\MondayChurnBoardClientService;

//
// @DEPRECATED 29/04/2025, borrar cuando pueda
//
class MondayChurnBoardClientsSetClientyLeadIdCommand extends Command
{

    protected $signature = 'monday-churn-board-clients:set-clienty-lead-id';
    protected $description = 'Set clientyLeadId in Monday stored churn board clients';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $index = 0;
        $offset = 0;
        $limit = 100;
        $queryBuilder = MondayChurnBoardClient::limit($limit);
        $mondayChurnClients = (clone $queryBuilder)->offset($offset)->get();

        while ($mondayChurnClients->isNotEmpty()) {
            foreach ($mondayChurnClients as $mondayClient) {
                $index++;

                if ($mondayClient->clientyLeadIds) {
                    $this->printClientyLeadIdsAlreadySettedMsg($index, $mondayClient);
                    continue;
                }

                $mondayClientEmailStr = $mondayClient->formattedValues['email'] ?? null;
                if (!$mondayClientEmailStr) {
                    $this->printNonExistentMondayClientEmailMsg($index, $mondayClient);
                    continue;
                }

                $emailHash = LeadContactEmail::buildHash($mondayClientEmailStr);
                $leadContactEmails = LeadContactEmail::where('hash', $emailHash)->where('client_id', 2)->get();
                if ($leadContactEmails->isEmpty()) {
                    $this->printNonExistentClientyLeadEmailMsg($index, $mondayClient);
                    continue;
                }
                
                $clientyLeadIds = $leadContactEmails->pluck('lead_id')->unique()->toArray();
                $mondayClient->clientyLeadIds = $clientyLeadIds;
                $mondayClient->save();
                $this->printSuccessMatchMsg($index, $mondayClient, $clientyLeadIds);
            }

            $offset = $offset + $limit;
            $mondayChurnClients = (clone $queryBuilder)->offset($offset)->get();
        }
    }


    private function printClientyLeadIdsAlreadySettedMsg(
        int $index,
        MondayChurnBoardClient $mondayChurnBoardClient
    ): void {
        $msg = "{$index} - clientyLeadIds already setted | name: {$mondayChurnBoardClient->getName()}";
        // $this->info($msg);
    }


    private function printNonExistentMondayClientEmailMsg(
        int $index,
        MondayChurnBoardClient $mondayChurnBoardClient
    ): void {
        $msg = "{$index} - Client with NO email | name: {$mondayChurnBoardClient->getName()}";
        $this->error($msg);
    }


    private function printNonExistentClientyLeadEmailMsg(
        int $index,
        MondayChurnBoardClient $mondayChurnBoardClient
    ): void {
        $msg = "{$index} - Client email does not exist in Clienty | name: {$mondayChurnBoardClient->getName()}";
        $msg .= " | email: {$mondayChurnBoardClient->formattedValues['email']}";
        $this->error($msg);
    }


    private function printSuccessMatchMsg(
        int $index,
        MondayChurnBoardClient $mondayChurnBoardClient,
        array $clientyLeadIds
    ) {
        $clientyLeadIdsStr = implode(', ', $clientyLeadIds);
        $msg = "{$index} - Client mached successfully | name: {$mondayChurnBoardClient->getName()}";
        $msg .= " | email: {$mondayChurnBoardClient->formattedValues['email']}";
        $msg .= " | clientyLeadIds {$clientyLeadIdsStr}";
        $this->info($msg);
    }

}
