<?php

namespace App\Repositories\Automations;

use Exception;
use App\Models\Client;
use App\Models\AutomationTask;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\DTO\Automations\AutomationTaskDTO;
use App\DTO\Automations\Parameters\ListAutomationTaskDTO;


class AutomationTaskRepository
{

    public function list(ListAutomationTaskDTO $dto): Collection
    {
        // @TODO: Aplicar misma lógica que Views/LeadService::list
        $automations = AutomationTask::where('client_id', $dto->client->id)->get();
        return $automations;
    }


    public function findByClient(Client $client)
    {
        return AutomationTask::where('client_id', $client->id)->get();
    }


    public function findOneByClientAndTrigger(Client $client, string $triggerType): ?AutomationTask
    {
        return AutomationTask::where(['client_id' => $client->id, 'trigger_type' => $triggerType])->first();
    }


    public function findByClientAndTrigger(Client $client, string $triggerType): Collection
    {
        return AutomationTask::where(['client_id' => $client->id, 'trigger_type' => $triggerType])->get();
    }


    public function findOthersByClientAndTriggerType(
        AutomationTask $automation,
        Client $client,
        string $triggerType
    ): Collection {
        return AutomationTask::where('id', '!=', $automation->id)->where([
            'client_id' => $client->id, 'trigger_type' => $triggerType
        ])->get();
    }


    public function findEnabledAutomationTaskAfterSaleByClient(Client $client): Collection
    {
        return AutomationTask::where([
            'enabled' => true, 'client_id' => $client->id, 'trigger_type' => 'after_sale'
        ])->get();
    }


    public function findEnabledAutomationTaskAfterTaskExpirationByClient(Client $client): Collection
    {
        return AutomationTask::where([
            'enabled' => true, 'client_id' => $client->id, 'trigger_type' => 'after_task_expiration'
        ])->get();
    }


    public function findEnabledAfterTagStatusChangeAutomationByClient(Client $client): Collection
    {
        return AutomationTask::where('enabled', true)
            ->where('client_id', $client->id)
            ->where('enabled', true)
            ->where(function ($q) {
                $q->where('trigger_type', 'after_status_change')->orWhere('trigger_type', 'after_tag_change');
            })
        ->get();
    }


    public function create(AutomationTaskDTO $dto): AutomationTask
    {
        $data = [
            'enabled' => $dto->enabled,
            'client_id' => $dto->client->id,
            'create_hour' => $dto->createHour,
            'is_recurrent' => $dto->isRecurrent,
            'trigger_type' => $dto->triggerType,
            'task_template_id' => $dto->taskTemplateId,
            'create_delay_days' => $dto->createDelayDays,
            'status_id_to_assign' => $dto->statusToAssign?->id,
            'allowing_tags_ids' => $dto->allowingTags->pluck('id'),
            'is_immediately_created' => $dto->isImmediatelyCreated,
            'tags_ids_to_assign' => $dto->tagsToAssign->pluck('id'),
            'allowing_status_ids' => $dto->allowingStatus->pluck('id'),
            'triggering_tags_ids' => $dto->triggeringTags->pluck('id'),
            'cancelling_tags_ids' => $dto->cancellingTags->pluck('id'),
            'cancelling_status_ids' => $dto->cancellingStatus->pluck('id'),
            'triggering_status_ids' => $dto->triggeringStatus->pluck('id'),
        ];
        $automationTask = new AutomationTask($data);
        $automationTask->saveOrFail();
        return $automationTask->fresh();
    }


    public function update(AutomationTask $automationTask, AutomationTaskDTO $dto): AutomationTask
    {
        $data = [
            'enabled' => $dto->enabled,
            'client_id' => $dto->client->id,
            'create_hour' => $dto->createHour,
            'is_recurrent' => $dto->isRecurrent,
            'trigger_type' => $dto->triggerType,
            'task_template_id' => $dto->taskTemplateId,
            'create_delay_days' => $dto->createDelayDays,
            'status_id_to_assign' => $dto->statusToAssign?->id,
            'allowing_tags_ids' => $dto->allowingTags->pluck('id'),
            'tags_ids_to_assign' => $dto->tagsToAssign->pluck('id'),
            'is_immediately_created' => $dto->isImmediatelyCreated,
            'triggering_tags_ids' => $dto->triggeringTags->pluck('id'),
            'allowing_status_ids' => $dto->allowingStatus->pluck('id'),
            'cancelling_tags_ids' => $dto->cancellingTags->pluck('id'),
            'cancelling_status_ids' => $dto->cancellingStatus->pluck('id'),
            'triggering_status_ids' => $dto->triggeringStatus->pluck('id'),
        ];
        $automationTask->fill($data);
        $automationTask->saveOrFail();
        return $automationTask->fresh();
    }


    public function delete(AutomationTask $automationTask): AutomationTask
    {
        $automationTask->delete();
        return $automationTask->fresh();
    }


    public function enable(AutomationTask $automationTask): AutomationTask
    {
        $automationTask->enabled = true;
        $automationTask->saveOrFail();
        return $automationTask->fresh();
    }


    public function disable(AutomationTask $automationTask): AutomationTask
    {
        $automationTask->enabled = false;
        $automationTask->saveOrFail();
        return $automationTask->fresh();
    }

}
