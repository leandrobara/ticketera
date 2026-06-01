<?php

namespace App\Repositories\WAutomations;

use Exception;
use App\Models\Client;
use App\Models\WAutomationProposal;
use App\Exceptions\DatabaseException;
use App\DTO\WAutomations\WAutomationProposalDTO;


class WAutomationProposalRepository
{

    public function findOrCreateByClient(Client $client)
    {
        return WAutomationProposal::firstOrCreate(['client_id' => $client->id]);
    }


    public function findByClient(Client $client)
    {
        return WAutomationProposal::where(['client_id' => $client->id])->first();
    }


    public function update(WAutomationProposal $wAutomation, WAutomationProposalDTO $dto)
    {
        $wAutomation->fill(['enabled' => $dto->enabled]);
        $wAutomation->saveOrFail();
        return $wAutomation->fresh();
    }

}
