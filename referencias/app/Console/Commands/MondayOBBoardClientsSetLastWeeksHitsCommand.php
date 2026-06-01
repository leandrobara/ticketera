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
class MondayOBBoardClientsSetLastWeeksHitsCommand extends Command
{

    public $logUuid;
    protected $description = 'Update Monday "Celula - OB" board: set last weeks hits';
    protected $signature = 'monday-ob-board-clients:set-last-weeks-hits {--use-cache=}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        // DEPRECADO
        return true;


        $this->logUuid = 'SetOBHits-' . Str::afterLast(Str::orderedUuid(), '-');

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

        //dd($boardClients->first());
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

        $weeksData = [
            [
                'mondayColumnId' => 'n_meros',
                'dtoAttributeName' => 'hitsCountLastWeek',
                'mondayColumnName' => 'Hits de última semana',
                'filters' => [
                    'date_end' => new DateTime('now'),
                    'date_start' => new DateTime('7 days ago'),
                ],
            ],
            [
                'mondayColumnId' => 'n_meros8__1',
                'dtoAttributeName' => 'hitsCountTwoWeeksAgo',
                'mondayColumnName' => 'Hits de hace 2 semanas',
                'filters' => [
                    'date_end' => new DateTime('7 days ago'),
                    'date_start' => new DateTime('14 days ago'),
                ],
            ],
            [
                'mondayColumnId' => 'n_meros4__1',
                'dtoAttributeName' => 'hitsCountFourWeeksAgo',
                'mondayColumnName' => 'Hits de hace 3 semanas',
                'filters' => [
                    'date_end' => new DateTime('14 days ago'),
                    'date_start' => new DateTime('21 days ago'),
                ],
            ],
            [
                'mondayColumnId' => 'n_meros6__1',
                'dtoAttributeName' => 'hitsCountThreeWeeksAgo',
                'mondayColumnName' => 'Hits de hace 4 semanas',
                'filters' => [
                    'date_end' => new DateTime('21 days ago'),
                    'date_start' => new DateTime('28 days ago'),
                ],
            ],
        ];

        $usageReportService = resolve(ClientUsageReportService::class);
        foreach ($enabledClientsDtos as $i => $dto) {
            $msg = "\n-----------\n";
            $msg .= "Client '{$dto->name}' (Board Item ID: {$dto->id}) \n";
            $msg .= "-----------\n";
            $msg .= "[CURRENT CLIENTY HITS METRICS]";
            $this->logInfo($msg);

            foreach ($weeksData as $weekData) {
                $opts = ['filters' => $weekData['filters']];
                $weekHitsReport = $usageReportService->clientLevelReport($dto->clientyClient, $opts);
                $totalWeekHitsCount = $weekHitsReport['totalHits'] - $weekHitsReport['automaticLeadsCount'];
                
                $dtoAttributeName = $weekData['dtoAttributeName'];
                $dto->$dtoAttributeName = $totalWeekHitsCount;

                $this->logInfo("  - {$weekData['mondayColumnName']}: \"{$dto->$dtoAttributeName}\"");
            }

            $dto->averageHits = (int) ($dto->getHitsSum() / count($weeksData));
            $this->logInfo("  - Promedio hits: {$dto->averageHits}");

            try {
                $this->logInfo("[MONDAY UPDATE STARTING]");
                
                $mondayAPIHelper->updateCelulaOBLastWeeksHits($dto);
                
                $this->logInfo("[MONDAY UPDATE SUCCESS] \n-----------\n");
            } catch (Exception $e) {
                $msg = "- [EXCEPTION ERROR] Client '{$dto->name}' (Board ID: {$dto->id}) || {$e->getMessage()}\n";
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
