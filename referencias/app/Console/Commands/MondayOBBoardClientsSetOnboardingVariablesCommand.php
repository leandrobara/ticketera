<?php

namespace App\Console\Commands;

use DateTime;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Helpers\MondayAPIHelper;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Models\MongoDB\MondayChurnBoardClient;
use App\DTO\Monday\MondayAPICelulaOBBoardClientDTO;
use App\Services\API\Views\Reports\ClientyConfigurations\ClientUsageReportService;


class MondayOBBoardClientsSetOnboardingVariablesCommand extends Command
{

    public $logUuid;
    protected $signature = 'monday-ob-board-clients:set-onboarding-variables {--avoid-cache=}';
    protected $description = 'Update Monday "Celula - OB" board: set onboarding variables';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $this->logUuid = 'SetOBVariables-' . Str::afterLast(Str::orderedUuid(), '-');

        $mondayAPIHelper = resolve(MondayAPIHelper::class);

        $avoidCache = $this->option('avoid-cache') ? true : false;
        if ($avoidCache) {
            $boardClients = $mondayAPIHelper->listOBBoardItems(['limit' => 99999]);
        } else {
            $boardClients = Cache::store('redis')->get('MondayOBBoardItems');
            if (!$boardClients) {
                $boardClients = $mondayAPIHelper->listOBBoardItems(['limit' => 99999]);
                Cache::store('redis')->set('MondayOBBoardItems', $boardClients, 3600 * 23);
            }
        }

        $enabledClientsDtos = [];
        foreach ($boardClients as $i => $boardClientData) {
            $dto = new MondayAPICelulaOBBoardClientDTO($boardClientData);
            if (!$dto->subdomain) {
                $this->logInfo("[DISCARDED] - Client {$dto->name} has no subdomain");
                continue;
            }
            if ($dto->status != 'ONBOARDING' && $dto->status != 'ACTIVACION') {
                $this->logInfo("[DISCARDED] - Client {$dto->name} status is not 'ONBOARDING' or 'ACTIVACION'");
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
        
        $usageReportService = resolve(ClientUsageReportService::class);
        foreach ($enabledClientsDtos as $i => $dto) {
            $opts = [
                'filters' => [
                    'date_start' => new DateTime('20 days ago'), 'date_end' => new DateTime('now'),
                ],
            ];
            $report = $usageReportService->clientLevelReport($dto->clientyClient, $opts);
            
            $manualLeadsCount = $report['manualLeadsCount'];
            $statusChangesCount = $report['statusChangeCount'];
            $automaticLeadsCount = $report['automaticLeadsCount'];
            $totalHitsCount = $report['totalHits'] - $report['automaticLeadsCount'];

            $totalHitsFlagIsSuccess = !$dto->totalHitsFlagIsSuccess && $totalHitsCount >= 50;
            $manualLeadsFlagIsSuccess = !$dto->manualLeadsFlagIsSuccess && $manualLeadsCount >= 50;
            $statusChangesFlagIsSuccess = !$dto->statusChangesFlagIsSuccess && $statusChangesCount >= 15;
            $automaticLeadsFlagIsSuccess = !$dto->automaticLeadsFlagIsSuccess && $automaticLeadsCount >= 5;


            $msg = "\n----------- \n";
            $msg .= "Client '{$dto->name}' (Board Item ID: {$dto->id}) \n";
            $msg .= "----------- \n";
            $msg .= "[MONDAY DATA] \n";
            $msg .= "  - BBDD: \"{$dto->manualLeadsFlagStr}\" \n";
            $msg .= "  - Hits 20 días: \"{$dto->totalHitsFlagStr}\" \n";
            $msg .= "  - Cambia estados: \"{$dto->statusChangesFlagStr}\" \n";
            $msg .= "  - Carga automática: \"{$dto->automaticLeadsFlagStr}\" \n";

            try {
                $isSuccessRequest = $mondayAPIHelper->updateOBBoardItemOnboardingVariables(
                    boardItemId: $dto->id,
                    totalHitsFlagIsSuccess: $totalHitsFlagIsSuccess,
                    manualLeadsFlagIsSuccess: $manualLeadsFlagIsSuccess,
                    statusChangesFlagIsSuccess: $statusChangesFlagIsSuccess,
                    automaticLeadsFlagIsSuccess: $automaticLeadsFlagIsSuccess,
                );
                $msg .= "[CURRENT CLIENTY CLIENT METRICS] \n";
                $msg .= "  - totalHitsCount: {$totalHitsCount} \n";
                $msg .= "  - manualLeadsCount: {$manualLeadsCount} \n";
                $msg .= "  - statusChangesCount: {$statusChangesCount} \n";
                $msg .= "  - automaticLeadsCount: {$automaticLeadsCount} \n";
                if ($isSuccessRequest) {
                    $msg .= "[MONDAY UPDATED VALUES] \n";
                    $msg .= $automaticLeadsFlagIsSuccess ? "  - BBDD: \"Realizado\" \n" : '';
                    $msg .= $statusChangesFlagIsSuccess ? "  - Hits 20 días: \"Realizado\" \n" : '';
                    $msg .= $manualLeadsFlagIsSuccess ? "  - Cambia estados: \"Realizado\" \n" : '';
                    $msg .= $totalHitsFlagIsSuccess ? "  - Carga automática: \"Realizado\" \n" : '';
                } else {
                    $msg .= "[MONDAY UPDATE NOT REQUIRED] \n";
                }
                $msg .= "----------- \n\n";
                $this->logInfo($msg);
            } catch (Exception $e) {
                $msg = "- [EXCEPTION ERROR] Client '{$dto->name}' (Board ID: {$dto->id}) || {$e->getMessage()}\n";
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
