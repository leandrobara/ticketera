<?php

namespace App\Repositories\Automations;

use Exception;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\AutomationEmailSend;
use App\Exceptions\DatabaseException;
use App\Models\AutomationEmailSendStep;
use App\DTO\Automations\AutomationEmailSendStepDTO;


class AutomationEmailSendStepRepository implements Repository
{

    public function findByAutomationEmailSend(AutomationEmailSend $automationEmailSend): Collection
    {
        return AutomationEmailSendStep::where('automation_email_send_id', $automationEmailSend->id)
            ->orderBy('send_delay_days', 'asc')
            ->orderBy('send_delay_minutes', 'asc')
            ->get()
        ;
    }


    public function create(AutomationEmailSendStepDTO $dto): AutomationEmailSendStep
    {
        $addTagIds = $dto->tagsToAdd->isNotEmpty() ? $dto->tagsToAdd->pluck('id') : null;
        $data = [
            'add_tags_ids' => $addTagIds,
            'send_hour' => $dto->sendHour,
            'client_id' => $dto->client->id,
            'add_status_id' => $dto->statusToAdd?->id,
            'send_delay_days' => $dto->sendDelayDays,
            'send_delay_minutes' => $dto->sendDelayMinutes,
            'send_email_template_id' => $dto->sendEmailTemplate->id,
            'automation_email_send_id' => $dto->automationEmailSend->id,
        ];
        $step = new AutomationEmailSendStep($data);
        $step->saveOrFail();
        return $step->fresh();
    }


    public function update(AutomationEmailSendStep $step, AutomationEmailSendStepDTO $dto): AutomationEmailSendStep
    {
        $addTagIds = $dto->tagsToAdd->isNotEmpty() ? $dto->tagsToAdd->pluck('id') : null;
        $data = [
            'add_tags_ids' => $addTagIds,
            'send_hour' => $dto->sendHour,
            'client_id' => $dto->client->id,
            'send_delay_days' => $dto->sendDelayDays,
            'add_status_id' => $dto->statusToAdd?->id,
            'send_delay_minutes' => $dto->sendDelayMinutes,
            'send_email_template_id' => $dto->sendEmailTemplate->id,
            'automation_email_send_id' => $dto->automationEmailSend->id,
        ];
        $step->fill($data);
        $step->saveOrFail();
        return $step->fresh();
    }


    public function delete(AutomationEmailSendStep $step): AutomationEmailSendStep
    {
        $step->delete();
        return $step->fresh();
    }


    public function deleteAllByAutomationEmailSend(AutomationEmailSend $automation): bool
    {
        $automation->automationEmailSendSteps()->delete();
        return true;
    }

}
