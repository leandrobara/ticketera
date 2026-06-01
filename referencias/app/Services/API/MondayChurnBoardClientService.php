<?php

namespace App\Services\API;

use DateTime;
use Exception;
use Illuminate\Support\Collection;
use App\Models\MongoDB\MondayChurnBoardClient;
use App\DTO\Monday\MondayAPIChurnBoardClientDTO;
use App\Repositories\MondayChurnBoardClientRepository;
// use App\Repositories\Criteria\Sort\EventLogs\SortByCreated;
// use App\Repositories\Criteria\Filter\EventLogs\CreatedDateEndCriteria;
// use App\Repositories\Criteria\Filter\EventLogs\CreatedDateStartCriteria;


//
// @DEPRECATED 29/04/2025, borrar cuando pueda
//
class MondayChurnBoardClientService
{

    public function __construct(
        protected readonly MondayChurnBoardClientRepository $mondayChurnBoardClientRepository
    ) {
    }


    public function create(MondayAPIChurnBoardClientDTO $dto): MondayChurnBoardClient
    {
        return $this->mondayChurnBoardClientRepository->create($dto->toArray());
    }


    public function update(
        MondayChurnBoardClient $mondayChurnBoardClient,
        MondayAPIChurnBoardClientDTO $dto
    ): MondayChurnBoardClient {
        return $this->mondayChurnBoardClientRepository->update($mondayChurnBoardClient, $dto->toArray());
    }


    public function findOneByExternalId(string $externalId): ?MondayChurnBoardClient
    {
        $mondayChurnBoardClient = $this->mondayChurnBoardClientRepository->findOneByExternalId($externalId);
        return $mondayChurnBoardClient;
    }


    public function findOneByLeadId(int $leadId): ?MondayChurnBoardClient
    {
        return $this->mondayChurnBoardClientRepository->findOneByLeadId($leadId);
    }

}
