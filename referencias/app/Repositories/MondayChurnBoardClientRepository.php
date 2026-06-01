<?php

namespace App\Repositories;

use DateTime;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Eloquent\Builder;
use App\Models\MongoDB\MondayChurnBoardClient;
use App\Repositories\Criteria\Sort\MongoSortCriteria;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


//
// @DEPRECATED 29/04/2025, borrar cuando pueda
//
class MondayChurnBoardClientRepository
{

    public function findOneByExternalId(string $externalId): ?MondayChurnBoardClient
    {
        $mondayChurnBoardClient = MondayChurnBoardClient::where('externalId', $externalId)->first();
        return $mondayChurnBoardClient;
    }


    public function findOneByLeadId(int $leadId): ?MondayChurnBoardClient
    {
        return MondayChurnBoardClient::where('clientyLeadIds', $leadId)->first();
    }


    public function create(array $mondayChurnBoardClientData): MondayChurnBoardClient
    {
        $mondayChurnBoardClient = new MondayChurnBoardClient($mondayChurnBoardClientData);
        $mondayChurnBoardClient->hash = $mondayChurnBoardClient->buildHash();
        $this->validateStoreData($mondayChurnBoardClient);

        $mondayChurnBoardClient->save();
        return $mondayChurnBoardClient->fresh();
    }


    public function update(
        MondayChurnBoardClient $mondayChurnBoardClient,
        array $mondayChurnBoardClientData
    ): MondayChurnBoardClient {
        $mondayChurnBoardClient->fill($mondayChurnBoardClientData);
        $mondayChurnBoardClient->hash = $mondayChurnBoardClient->buildHash();
        
        $this->validateStoreData($mondayChurnBoardClient);

        $mondayChurnBoardClient->save();
        return $mondayChurnBoardClient->fresh();
    }


    protected function validateStoreData(MondayChurnBoardClient $mondayChurnBoardClient): void
    {
        // if (!$scheduledEvent->calendar_event) {
        //     throw new Exception('scheduled_event_calendar_event_is_empty');
        // }
    }

}
