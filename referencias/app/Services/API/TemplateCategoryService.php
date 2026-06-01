<?php

namespace App\Services\API;

use Exception;
use App\Models\Client;
use App\Models\TemplateCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\TemplateCategoryRepository;


class TemplateCategoryService
{

    use GetClientFromRequest;

    
    public function __construct(
        private readonly TemplateCategoryRepository $templateCategoryRepository
    ) {
        //
    }


    public function create($data)
    {
        $client = $data['client'] ?? $this->getClient();
        $data['client_id'] = $client->id;
        unset($data['client']);

        return $this->templateCategoryRepository->create($data);
    }


    public function update(TemplateCategory $templateCategory, array $data)
    {
        return $this->templateCategoryRepository->update($templateCategory, $data);
    }


    public function find(int $id): ?TemplateCategory
    {
        return $this->templateCategoryRepository->find($id);
    }


    public function findAllByClient(?Client $client = null)
    {
        $client = (!$client) ? $this->getClient() : $client;
        return $this->templateCategoryRepository->findAllByClient($client);
    }


    public function delete(TemplateCategory $templateCategory)
    {
        return $this->templateCategoryRepository->delete($templateCategory);
    }


    public function getRelatedTemplatesCountInfo(TemplateCategory $templateCategory): array
    {
        $taskTemplateCount = $templateCategory->taskTemplateCount;
        $emailTemplateCount = $templateCategory->emailTemplateCount;
        $whatsAppTemplateCount = $templateCategory->whatsAppTemplateCount;
        $totalRelatedTemplatesCount = (
            $taskTemplateCount + $emailTemplateCount + $whatsAppTemplateCount
        );
        return [
            'total' => $totalRelatedTemplatesCount,
            'taskTemplateCount' => $taskTemplateCount,
            'emailTemplateCount' => $emailTemplateCount,
            'whatsAppTemplateCount' => $whatsAppTemplateCount,
        ];
    }

}
