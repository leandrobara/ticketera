<?php

namespace App\Services\API;

use App\Models\User;
use App\Models\Client;
use App\Models\TaskTemplate;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Traits\GetClientFromRequest;


class TaskTemplateService
{

    use GetClientFromRequest;

    private $taskTemplateRepository;


    public function __construct(Repository $taskTemplateRepository)
    {
        $this->taskTemplateRepository = $taskTemplateRepository;
    }


    public function findAllByClient(): Collection
    {
        return $this->taskTemplateRepository->findAllByClient($this->getClient());
    }


    public function findOneByTaskTemplateIdAndClient(int $taskTemplateId, Client $client): ?TaskTemplate
    {
        return $this->taskTemplateRepository->findOneByTaskTemplateIdAndClient($taskTemplateId, $client);
    }


    public function findOneByClientAndTemplateName(Client $client, string $templateName): ?TaskTemplate
    {
        return $this->taskTemplateRepository->findOneByClientAndTemplateName($client, $templateName);
    }


    public function create($data): TaskTemplate
    {
        $data['client_id'] = $this->getClient()->id;
        return $this->taskTemplateRepository->create($data);
    }


    public function update(TaskTemplate $taskTemplate, $data): TaskTemplate
    {
        return $this->taskTemplateRepository->update($taskTemplate, $data);
    }


    public function delete(TaskTemplate $taskTemplate): TaskTemplate
    {
        return $this->taskTemplateRepository->delete($taskTemplate);
    }

}
