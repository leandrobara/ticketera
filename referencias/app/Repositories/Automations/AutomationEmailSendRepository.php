<?php

namespace App\Repositories\Automations;

use Exception;
use App\Models\Client;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\AutomationEmailSend;
use App\DTO\Automations\AutomationEmailSendDTO;


class AutomationEmailSendRepository implements Repository
{

    public function findByClient(Client $client): Collection
    {
        return AutomationEmailSend::where('client_id', $client->id)->get();
    }


    public function findOneByClientAndTrigger(Client $client, string $trigger): ?AutomationEmailSend
    {
        return AutomationEmailSend::where(['client_id' => $client->id, 'trigger_type' => $trigger])->first();
    }


    public function findByClientAndTrigger(Client $client, string $trigger): Collection
    {
        return AutomationEmailSend::where(['client_id' => $client->id, 'trigger_type' => $trigger])->get();
    }


    public function create(AutomationEmailSendDTO $dto): AutomationEmailSend
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
        $automationEmailSend = new AutomationEmailSend($data);
        $automationEmailSend->saveOrFail();
        return $automationEmailSend->fresh();
    }


    public function update(AutomationEmailSend $automationEmailSend, AutomationEmailSendDTO $dto): AutomationEmailSend
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
        $automationEmailSend->fill($data);
        $automationEmailSend->saveOrFail();
        return $automationEmailSend->fresh();
    }


    public function delete(AutomationEmailSend $automationEmailSend): AutomationEmailSend
    {
        $automationEmailSend->delete();
        return $automationEmailSend->fresh();
    }


    public function enable(AutomationEmailSend $automationEmailSend): AutomationEmailSend
    {
        $automationEmailSend->enabled = true;
        $automationEmailSend->saveOrFail();
        return $automationEmailSend->fresh();
    }


    public function disable(AutomationEmailSend $automationEmailSend): AutomationEmailSend
    {
        $automationEmailSend->enabled = false;
        $automationEmailSend->saveOrFail();
        return $automationEmailSend->fresh();
    }

}
