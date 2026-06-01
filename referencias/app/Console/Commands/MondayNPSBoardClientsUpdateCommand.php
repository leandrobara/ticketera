<?php

namespace App\Console\Commands;

use DateTime;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use App\Helpers\MondayAPIHelper2;
use Illuminate\Support\Facades\Log;
use App\Services\API\ClientService;
use App\Services\API\NPSPollService;
use Illuminate\Support\Facades\Cache;
use App\DTO\Monday\MondayNPSBoardItemDTO;


class MondayNPSBoardClientsUpdateCommand extends Command
{

    public $logUuid;

    protected $signature = 'monday-nps-board-clients:update ' .
        '{--use-cache=} {--poll-type=} {--client-id=} {--min-client-id=}'
    ;
    protected $description = 'Update Monday "NPS/CSAT" board: set onboarding variables';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $pollType = $this->option('poll-type');
        $clientId = $this->option('client-id');
        $minClientId = $this->option('min-client-id');
        $pollTypeUpper = strtoupper($pollType);
        $useCache = $this->option('use-cache') ? true : false;
        $this->logUuid = "{$pollTypeUpper}-Board-" . Str::afterLast(Str::orderedUuid(), '-');

        $mondayGroupIdConstant = MondayNPSBoardItemDTO::NPS_GROUP_ID;
        $mondayGroupNameConstant = MondayNPSBoardItemDTO::NPS_GROUP_NAME;
        if ($pollType == 'csat') {
            $mondayGroupIdConstant = MondayNPSBoardItemDTO::CSAT_GROUP_ID;
            $mondayGroupNameConstant = MondayNPSBoardItemDTO::CSAT_GROUP_NAME;
        }

        $clientService = resolve(ClientService::class);
        $NPSPollService = resolve(NPSPollService::class);
        $mondayAPIHelper = resolve(MondayAPIHelper2::class);

        if (!$pollType || !in_array($pollType, ['nps', 'csat'])) {
            $this->error('The --poll-type option is required and must be either "nps" or "csat"');
            return 1;
        }

        if ($useCache) {
            $boardItems = Cache::store('redis')->get('MondayNPSBoardItems');
            if (!$boardItems) {
                $boardItems = $mondayAPIHelper->listNPSBoardItems(['limit' => 99999]);
                Cache::store('redis')->set('MondayNPSBoardItems', $boardItems, 3600 * 23);
            }
        } else {
            $boardItems = $mondayAPIHelper->listNPSBoardItems(['limit' => 99999]);
        }

        $mondayNPSDtos = $boardItems->map(function ($item) {
            return MondayNPSBoardItemDTO::buildFromMondayItemData($item);
        });

        $clients = $clientService->findAllEnabled();
        if ($clientId) {
            $clients = $clients->where('id', $clientId);
        }
        if ($minClientId) {
            $clients = $clients->where('id', '>=', $minClientId);
        }

        foreach ($clients as $client) {
            $lastNPSPoll = $NPSPollService->findLastByTargetedClient($client, ['filters' => ['type' => $pollType]]);
            if (!$lastNPSPoll) {
                $this->logInfo(
                    "[DISCARDED] - Client \"{$client->name}\" (ID: {$client->id}) has no {$pollTypeUpper} poll"
                );
                continue;
            }
            foreach ($client->enabledUsers as $user) {
                $lastNPSPollAnswer = $lastNPSPoll->getNPSPollAnswerByUser($user);
                if (!$lastNPSPollAnswer) {
                    $msg = "[DISCARDED] - User \"{$user->name} {$user->last_name}\" ";
                    $msg .= "(ID: {$user->id}) has no {$pollTypeUpper} poll";
                    $this->logInfo($msg);
                    continue;
                }

                $NPSItemDto = $mondayNPSDtos->where('clientyUserId', $user->id)
                    ->where('groupName', $mondayGroupNameConstant)
                    ->first()
                ;
                if (!$NPSItemDto) {
                    $NPSItemDto = MondayNPSBoardItemDTO::buildFromClientyUser($user, $mondayGroupIdConstant);
                }

                $msg = "\n-------------- \n";
                $msg .= "Client '{$NPSItemDto->name}' (ID: {$NPSItemDto->clientyClientId}) \n";
                $msg .= "User '{$user->name} {$user->last_name}' (ID: {$user->id}) \n";
                $msg .= "-------------- \n";
                $msg .= "[POLL DATA] \n";
                $msg .= "  - Monday ID: {$NPSItemDto->id} \n";
                $msg .= "  - Monday Board Group: " . $NPSItemDto->groupName . " \n";

                $NPSItemDto->fillColumnValuesByUserAndNPSPollAnswer($user, $lastNPSPollAnswer);
                foreach ($NPSItemDto->columnsToUpdate as $column) {
                    $val = is_array($column['value']) ? reset($column['value']) : $column['value'];
                    $msg .= "  - {$column['name']}: {$val} \n";
                }

                $msg .= "----";
                $this->logInfo($msg);

                $mondayAPIHelper->persistNPSPollItem($NPSItemDto, $mondayGroupIdConstant);

                $msg = $NPSItemDto->id ? "[UPDATED] \n" : "[CREATED] \n";
                $msg .= "-------------- \n\n";
                $this->logInfo($msg);
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
        return Log::channel('monday_nps_update_command_info');
    }

}