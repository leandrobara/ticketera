<?php

namespace App\Repositories\WAutomations;

use Exception;
use App\Models\Client;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\WAutomationSequence;
use App\Exceptions\DatabaseException;
use App\Repositories\Traits\VoidClearCache;
use App\DTO\WAutomations\WAutomationSequenceDTO;


class WAutomationSequenceRepository implements Repository
{

    use VoidClearCache;

    
    public function findByClient(Client $client): Collection
    {
        return WAutomationSequence::where('client_id', $client->id)->get();
    }


    public function findOneByClientAndTrigger(Client $client, string $trigger): ?WAutomationSequence
    {
        return WAutomationSequence::where(['client_id' => $client->id, 'trigger_type' => $trigger])->first();
    }


    public function findByClientAndTrigger(Client $client, string $trigger): Collection
    {
        return WAutomationSequence::where(['client_id' => $client->id, 'trigger_type' => $trigger])->get();
    }


    public function create(WAutomationSequenceDTO $dto): WAutomationSequence
    {
        $data = [
            'name' => $dto->name,
            'enabled' => $dto->enabled,
            'client_id' => $dto->client->id,
            'trigger_type' => $dto->triggerType,
            'do_not_send_weekends' => $dto->doNotSendWeekends,
            'triggering_tags_ids' => $dto->triggeringTags->pluck('id'),
            'cancelling_tags_ids' => $dto->cancellingTags->pluck('id'),
            'triggering_status_ids' => $dto->triggeringStatus->pluck('id'),
            'cancelling_status_ids' => $dto->cancellingStatus->pluck('id'),
            'cancel_if_sequence_was_sent' => $dto->cancelIfSequenceWasSent,
        ];
        $wAutomationSequence = new WAutomationSequence($data);
        $wAutomationSequence->saveOrFail();
        return $wAutomationSequence->fresh();
    }


    public function update(WAutomationSequence $wAutomationSequence, WAutomationSequenceDTO $dto)
    {
        $data = [
            'name' => $dto->name,
            'enabled' => $dto->enabled,
            'client_id' => $dto->client->id,
            'client_id'  => $dto->client->id,
            'trigger_type' => $dto->triggerType,
            'do_not_send_weekends' => $dto->doNotSendWeekends,
            'triggering_tags_ids' => $dto->triggeringTags->pluck('id'),
            'cancelling_tags_ids' => $dto->cancellingTags->pluck('id'),
            'triggering_status_ids' => $dto->triggeringStatus->pluck('id'),
            'cancelling_status_ids' => $dto->cancellingStatus->pluck('id'),
            'cancel_if_sequence_was_sent' => $dto->cancelIfSequenceWasSent,
        ];
        $wAutomationSequence->fill($data);
        $wAutomationSequence->saveOrFail();
        return $wAutomationSequence->fresh();
    }


    public function delete(WAutomationSequence $wAutomationSequence): WAutomationSequence
    {
        $wAutomationSequence->delete();
        return $wAutomationSequence->fresh();
    }


    public function enable(WAutomationSequence $wAutomationSequence): WAutomationSequence
    {
        $wAutomationSequence->enabled = true;
        $wAutomationSequence->saveOrFail();
        return $wAutomationSequence->fresh();
    }


    public function disable(WAutomationSequence $wAutomationSequence): WAutomationSequence
    {
        $wAutomationSequence->enabled = false;
        $wAutomationSequence->saveOrFail();
        return $wAutomationSequence->fresh();
    }

}
