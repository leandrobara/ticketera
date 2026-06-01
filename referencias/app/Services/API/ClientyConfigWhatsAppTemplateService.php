<?php

namespace App\Services\API;

use Exception;
use Throwable;
use App\Models\BusinessArea;
use App\Models\BusinessAreaChild;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DatabaseException;
use App\Models\ClientyConfigWhatsAppTemplate;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\ClientyConfigWhatsAppTemplateRepository;


class ClientyConfigWhatsAppTemplateService
{
    use GetClientFromRequest;

    private $clientyConfigWhatsAppTemplateRepository;


    public function __construct(ClientyConfigWhatsAppTemplateRepository $clientyConfigWhatsAppTemplateRepository)
    {
        $this->clientyConfigWhatsAppTemplateRepository = $clientyConfigWhatsAppTemplateRepository;
    }


    public function list(array $opts = [])
    {
        $options = [
            'order' => $this->getSortCriteriasByName($opts['order'] ?? ''),
            'filters' => $this->getFilterCriterias($opts['filters'] ?? []),
        ];
        $client = $this->getClient();
        $response = $this->clientyConfigWhatsAppTemplateRepository->list($client, $options);
        return $response;
    }


    public function findByBusinessAreaWithNoChild(BusinessArea $businessArea): Collection
    {
        $tpls = $this->clientyConfigWhatsAppTemplateRepository->findByBusinessAreaWithNoChild($businessArea);
        return $tpls;
    }


    public function findByBusinessAreaChild(BusinessAreaChild $businessAreaChild): Collection
    {
        $tpls = $this->clientyConfigWhatsAppTemplateRepository->findByBusinessAreaChild($businessAreaChild);
        return $tpls;
    }


    public function findForAllBusinessArea(): Collection
    {
        $tpls = $this->clientyConfigWhatsAppTemplateRepository->findForAllBusinessArea();
        return $tpls;
    }


    public function create(array $data, array $opts = []): ClientyConfigWhatsAppTemplate
    {
        try {
            DB::beginTransaction();
            $whatsAppTemplate = $this->clientyConfigWhatsAppTemplateRepository->create($data);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $whatsAppTemplate = $whatsAppTemplate->fresh();
        return $whatsAppTemplate;
    }


    public function update(ClientyConfigWhatsAppTemplate $whatsAppTemplate, $data): ClientyConfigWhatsAppTemplate
    {
        try {
            DB::beginTransaction();
            $whatsAppTemplate = $this->clientyConfigWhatsAppTemplateRepository->update($whatsAppTemplate, $data);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        
        return $whatsAppTemplate->fresh();
    }


    public function delete(ClientyConfigWhatsAppTemplate $whatsAppTemplate)
    {
        return $this->clientyConfigWhatsAppTemplateRepository->delete($whatsAppTemplate);
    }


    protected function getFilterCriterias(array $filters)
    {
        $criterias = [];
        $nfilters = [];
        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias))) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] =  $value;
            }
        }
        return $nfilters;
    }


    private function getSortCriteriasByName(string $sortName)
    {
        $sortTypes = [];
        return $sortTypes[$sortName] ?? $sortName;
    }
}
