<?php

namespace App\Repositories\Automations;

use Exception;
use App\Models\Client;
use App\Models\AutomationProposal;
use App\Exceptions\DatabaseException;
use App\DTO\Automations\AutomationProposalDTO;


class AutomationProposalRepository
{

    public function findByClient(Client $client)
    {
        return AutomationProposal::where(['client_id' => $client->id])->first();
    }


    public function update(AutomationProposal $automation, AutomationProposalDTO $dto)
    {
        $automation->fill(['enabled' => $dto->enabled]);
        $automation->saveOrFail();
        return $automation->fresh();
    }

}
