<?php

namespace App\Repositories\WAutomations;

use Exception;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\WAutomationSequence;
use App\Exceptions\DatabaseException;
use App\Models\WAutomationSequenceStep;
use App\DTO\WAutomations\WAutomationSequenceStepDTO;


class WAutomationSequenceStepRepository implements Repository
{

    public function findByWAutomationSequence(WAutomationSequence $wAutomationSequence): Collection
    {
        return WAutomationSequenceStep::where('wautomation_sequence_id', $wAutomationSequence->id)
            ->orderBy('send_delay_days', 'asc')
            ->orderBy('send_delay_minutes', 'asc')
            ->get()
        ;
    }


    public function create(WAutomationSequenceStepDTO $dto): WAutomationSequenceStep
    {
        $addTagIds = $dto->tagsToAdd->isNotEmpty() ? $dto->tagsToAdd->pluck('id') : null;
        $data = [
            'add_tags_ids' => $addTagIds,
            'send_hour' => $dto->sendHour,
            'client_id' => $dto->client->id,
            'send_delay_days' => $dto->sendDelayDays,
            'add_status_id' => $dto->statusToAdd?->id,
            'send_delay_minutes' => $dto->sendDelayMinutes,
            'wautomation_sequence_id' => $dto->wAutomationSequence->id,
            'send_whatsapp_template_id' => $dto->sendWhatsAppTemplate->id,
        ];
        $step = new WAutomationSequenceStep($data);
        $step->saveOrFail();
        return $step->fresh();
    }


    public function update(WAutomationSequenceStep $step, WAutomationSequenceStepDTO $dto): WAutomationSequenceStep
    {
        $addTagIds = $dto->tagsToAdd->isNotEmpty() ? $dto->tagsToAdd->pluck('id') : null;
        $data = [
            'add_tags_ids' => $addTagIds,
            'send_hour' => $dto->sendHour,
            'client_id' => $dto->client->id,
            'send_delay_days' => $dto->sendDelayDays,
            'add_status_id' => $dto->statusToAdd?->id,
            'send_delay_minutes' => $dto->sendDelayMinutes,
            'wautomation_sequence_id' => $dto->wAutomationSequence->id,
            'send_whatsapp_template_id' => $dto->sendWhatsAppTemplate->id,
        ];
        $step->fill($data);
        $step->saveOrFail();
        return $step->fresh();
    }


    public function delete(WAutomationSequenceStep $step): WAutomationSequenceStep
    {
        $step->delete();
        return $step->fresh();
    }


    public function deleteAllByWAutomationSequence(WAutomationSequence $wAutomation): bool
    {
        $wAutomation->wAutomationSequenceSteps()->delete();
        return true;
    }

}
