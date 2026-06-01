<?php

namespace App\Console\Commands;

use DateTime;
use Exception;
use Illuminate\Console\Command;
use App\Helpers\MondayAPIHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use App\Models\MongoDB\MondayChurnBoardClient;
use App\DTO\Monday\MondayAPIChurnBoardClientDTO;
use App\Services\API\MondayChurnBoardClientService;



//
// @DEPRECATED 29/04/2025, borrar cuando pueda
//
class MondayChurnBoardClientsPersistLocallyCommand extends Command
{

    protected $signature = 'monday-churn-board-clients:persist-locally';
    protected $description = 'Get clients from Monday churn board vía API and persist them in MongoDB';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $mondayAPIHelper = resolve(MondayAPIHelper::class);
        $mondayChurnBoardClientService = resolve(MondayChurnBoardClientService::class);

        $churnBoardClients = $mondayAPIHelper->listChurnBoardItems();
        foreach ($churnBoardClients as $i => $mondayChurnBoardClientData) {
            $dto = new MondayAPIChurnBoardClientDTO($mondayChurnBoardClientData);
            $externalId = $dto->getExternalId();

            if (!$externalId) {
                $this->error('- [NO EXTERNAL ID]');
                continue;
            }
            if (!$dto->hasUnsubscribeStatus()) {
                $this->error("- [STATUS IS NOT 'BAJA'] | externalId: {$externalId}");
                continue;
            }
            if (!$dto->hasName() && !$dto->hasEmail()) {
                $this->error("- [HAS NO NAME AND NO EMAIL] | externalId: {$externalId}");
                continue;
            }

            $existentBoardClient = $mondayChurnBoardClientService->findOneByExternalId($externalId);
            if ($existentBoardClient) {
                $hash = (new MondayChurnBoardClient($dto->toArray()))->buildHash();
                
                if ($hash == $existentBoardClient->hash) {
                    $this->printExistentClientMsg($existentBoardClient);
                    continue;
                }

                $churnBoardClient = $mondayChurnBoardClientService->update($existentBoardClient, $dto);
                $this->printUpdatedClientMsg($existentBoardClient);
                continue;
            }
            
            $churnBoardClient = $mondayChurnBoardClientService->create($dto);
            $this->printPersistedMsg($churnBoardClient);
        }
    }

    
    private function printPersistedMsg(MondayChurnBoardClient $mondayChurnBoardClient): void
    {
        $msg = "- [PERSISTED] | ";
        $msg .= "ExternalId: {$mondayChurnBoardClient->externalId} | ";
        $msg .= "Name: {$mondayChurnBoardClient->getName()}";
        $this->info($msg);
    }
    

    private function printUpdatedClientMsg(MondayChurnBoardClient $mondayChurnBoardClient): void
    {
        $msg = "- [UPDATED] | ";
        $msg .= "ExternalId: {$mondayChurnBoardClient->externalId} | ";
        $msg .= "Name: {$mondayChurnBoardClient->getName()}";
        $this->info($msg);
    }


    private function printExistentClientMsg(MondayChurnBoardClient $mondayChurnBoardClient): void
    {
        $msg = "- [ALREADY EXISTENT] | ";
        $msg .= "ExternalId: {$mondayChurnBoardClient->externalId} | ";
        $msg .= "Name: {$mondayChurnBoardClient->getName()}";
        $this->error($msg);
    }

}
