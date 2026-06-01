<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\TaskTemplate;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\Repositories\Traits\VoidClearCache;


class TaskTemplateRepository implements Repository
{

    use VoidClearCache;


    public function findAllByClient(Client $client): Collection
    {
        return TaskTemplate::where('client_id', $client->id)->get();
    }


    public function findOneByClientAndTemplateName(Client $client, string $templateName): ?TaskTemplate
    {
        return TaskTemplate::where(['client_id' => $client->id, 'template_name' => $templateName])->first();
    }


    public function findOneByTaskTemplateIdAndClient(int $taskTemplateId, Client $client): ?TaskTemplate
    {
        return TaskTemplate::where(['id' => $taskTemplateId, 'client_id' => $client->id])->first();
    }


    public function create(array $data): TaskTemplate
    {
        $emailTemplate = new TaskTemplate($data);
        $emailTemplate->saveOrFail();
        return $emailTemplate->fresh();
    }


    public function update(TaskTemplate $emailTemplate, array $data): TaskTemplate
    {
        $emailTemplate->fill($data);
        $emailTemplate->saveOrFail();
        return $emailTemplate->fresh();
    }


    public function delete(TaskTemplate $emailTemplate): TaskTemplate
    {
        $emailTemplate->delete();
        return $emailTemplate->fresh();
    }

}
