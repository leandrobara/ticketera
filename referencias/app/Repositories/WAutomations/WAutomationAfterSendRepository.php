<?php

namespace App\Repositories\WAutomations;

use Exception;
use App\Models\Client;
use Illuminate\Support\Collection;
use App\Models\WAutomationAfterSend;
use App\Exceptions\DatabaseException;
use App\DTO\WAutomations\WAutomationAfterSendDTO;


class WAutomationAfterSendRepository
{

    public function findOneByClient(Client $client): ?WAutomationAfterSend
    {
        return $this->findOneByClientId($client->id);
    }


    public function findOneEnabledByClient(Client $client): ?WAutomationAfterSend
    {
        return $this->findOneEnabledByClientId($client->id);
    }


    public function findOneByClientId(int $clientId): ?WAutomationAfterSend
    {
        return WAutomationAfterSend::where('client_id', $clientId)->first();
    }


    public function findOneEnabledByClientId(int $clientId): ?WAutomationAfterSend
    {
        return WAutomationAfterSend::where('client_id', $clientId)->where('enabled', true)->first();
    }


    public function create(WAutomationAfterSendDTO $dto): WAutomationAfterSend
    {
        $data = [
            'enabled' => $dto->enabled,
            'client_id' => $dto->client->id,
            'add_new_note' => $dto->addNewNote,
            'new_note_text' => $dto->newNoteText,
            'add_tags_ids' => $dto->tagsToAdd->pluck('id'),
            'assign_status_id' => $dto->statusToAssign?->id,
            'remove_tags_ids' => $dto->tagsToRemove->pluck('id'),
            'remove_tags_ids' => $dto->tagsToRemove->pluck('id'),
            'onlyApplyToMassiveSendings' => $dto->onlyApplyToMassiveSendings,
        ];
        $wAutomation = new WAutomationAfterSend($data);
        $wAutomation->saveOrFail();
        return $wAutomation->fresh();
    }


    public function update(
        WAutomationAfterSend $wAutomation,
        WAutomationAfterSendDTO $dto
    ): WAutomationAfterSend {
        $data = [
            'enabled' => $dto->enabled,
            'client_id' => $dto->client->id,
            'add_new_note' => $dto->addNewNote,
            'new_note_text' => $dto->newNoteText,
            'apply_only_once' => $dto->applyOnlyOnce,
            'add_tags_ids' => $dto->tagsToAdd->pluck('id'),
            'assign_status_id' => $dto->statusToAssign?->id,
            'remove_tags_ids' => $dto->tagsToRemove->pluck('id'),
            'only_apply_to_massive_sendings' => $dto->onlyApplyToMassiveSendings,
        ];
        $wAutomation->fill($data);
        $wAutomation->saveOrFail();
        return $wAutomation->fresh();
    }


    public function delete(WAutomationAfterSend $wAutomation): WAutomationAfterSend
    {
        $wAutomation->delete();
        return $wAutomation->fresh();
    }

}
