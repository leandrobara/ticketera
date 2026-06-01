<?php

namespace App\Console\Commands;

use DateTime;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Helpers\MondayAPIHelper2;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Models\MongoDB\MondayChurnBoardClient;
use App\DTO\Monday\MondayAPICelulaOBBoardClientLastWeeksHitsDTO;
use App\Services\API\Views\Reports\ClientyConfigurations\ClientUsageReportService;

/**
 * DEPRECADO
 */
class MondayOBBoardClientsSetUsersUsageCommand extends Command
{

    public $logUuid;
    protected $signature = 'monday-ob-board-clients:set-users-usage {--use-cache=}';
    protected $description = 'Update Monday "Celula - OB" board: set users percentage of usage';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        // DEPRECADO
        return true;

        
        $this->logUuid = 'SetUsersUsage-' . Str::afterLast(Str::orderedUuid(), '-');

        $mondayAPIHelper = resolve(MondayAPIHelper2::class);

        $useCache = $this->option('use-cache') ? true : false;
        if ($useCache) {
            $boardClients = Cache::store('redis')->get('MondayOBBoardItems2');
            if (!$boardClients) {
                $boardClients = $mondayAPIHelper->listOBBoardItems(['limit' => 99999]);
                Cache::store('redis')->set('MondayOBBoardItems2', $boardClients, 3600 * 23);
            }
        } else {
            $boardClients = $mondayAPIHelper->listOBBoardItems(['limit' => 99999]);
        }

        $enabledClientsDtos = [];
        foreach ($boardClients as $i => $boardClientData) {
            $dto = new MondayAPICelulaOBBoardClientLastWeeksHitsDTO($boardClientData);
            if (!$dto->subdomain) {
                $this->logInfo("[DISCARDED] - Client {$dto->name} has no subdomain");
                continue;
            }
            $enabledStatusArr = ['ACTIVO', 'ACTIVACION', 'ONBOARDING', 'VIP - ACTIVO'];
            if (!in_array($dto->status, $enabledStatusArr)) {
                $enabledStatusStr = implode(' or ', $enabledStatusArr);
                $this->logInfo("[DISCARDED] - Client {$dto->name} status is not {$enabledStatusStr}");
                continue;
            }

            $clientyClient = resolve(ClientService::class)->findOneBySubdomain($dto->subdomain);
            if (!$clientyClient) {
                $this->logInfo("[DISCARDED] - Client {$dto->name} has no matching client");
                continue;
            }
            if (!$clientyClient->enabled) {
                $this->logInfo("[DISCARDED] - Client {$dto->name} is disabled in Clienty");
                continue;
            }
            $dto->clientyClient = $clientyClient;
            $enabledClientsDtos[] = $dto;
        }
        // dd($enabledClientsDtos);


        $usageReportService = resolve(ClientUsageReportService::class);
        foreach ($enabledClientsDtos as $i => $dto) {
            $msg = "\n-----------\n";
            $msg .= "Client '{$dto->clientyClient->name}' (ID: {$dto->clientyClient->id}) \n";
            $msg .= "-----------\n";
            $this->logInfo($msg);

            $opts = [
                'onlyEnabledUsers' => true,
                'filters' => ['date_start' => new DateTime('30 days ago'), 'date_end' => new DateTime()],
            ];

            $usersWithUsageCount = 0;
            $clientHitsReport = $usageReportService->userLevelReport($dto->clientyClient, $opts);
            foreach ($clientHitsReport as $userHitsReport) {
                $userHitsCount = $userHitsReport['totalHits'] - $userHitsReport['automaticLeadsCount'];
                if ($userHitsCount > 30) {
                    $usersWithUsageCount++;
                }
            }
            $enabledUsersCount = count($clientHitsReport);
            $usersUsagePercentage = (int) ($usersWithUsageCount * 100 / $enabledUsersCount);
            $this->logInfo("- Usuarios habilitados: {$enabledUsersCount}");
            $this->logInfo("- Usuarios con uso: {$usersWithUsageCount}");
            $this->logInfo("- Porcentaje de uso: {$usersUsagePercentage}%");

            try {
                $this->logInfo("[MONDAY UPDATE STARTING]");
                
                $mondayAPIHelper->updateUsersUsagePercentage($dto, $usersUsagePercentage);
                
                $this->logInfo("[MONDAY UPDATE SUCCESS] \n-----------\n");
            } catch (Exception $e) {
                dd($e);
                $msg = "- [EXCEPTION ERROR] Client '{$dto->clientyClient->name}' (ID: {$dto->clientyClient->id})";
                $msg .= "|| {$e->getMessage()}\n";
                $msg .= "-----------\n";
                $this->logInfo($msg);
                continue;
            }
        }
    }


    protected function logInfo(string $msg, bool $printConsoleInfo = true): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
        if ($printConsoleInfo) {
            $this->info($msg);
        }
    }


    private function getInfoLog()
    {
        return Log::channel('monday_set_onboarding_variables_command_info');
    }

}
