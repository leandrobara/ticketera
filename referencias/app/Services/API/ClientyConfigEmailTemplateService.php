<?php

namespace App\Services\API;

use Exception;
use Throwable;
use App\Models\User;
use App\Models\BusinessArea;
use App\Models\BusinessAreaChild;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DatabaseException;
use App\Models\ClientyConfigEmailTemplate;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\ClientyConfigEmailTemplateRepository;


class ClientyConfigEmailTemplateService
{
    use GetClientFromRequest;

    private $clientyConfigEmailTemplateRepository;


    public function __construct(ClientyConfigEmailTemplateRepository $clientyConfigEmailTemplateRepository)
    {
        $this->clientyConfigEmailTemplateRepository = $clientyConfigEmailTemplateRepository;
    }


    public function list(array $opts = [])
    {
        $options = [
            'order' => $this->getSortCriteriasByName($opts['order'] ?? ''),
            'filters' => $this->getFilterCriterias($opts['filters'] ?? []),
        ];
        $client = $this->getClient();
        $response = $this->clientyConfigEmailTemplateRepository->list($client, $options);
        return $response;
    }


    public function findByBusinessAreaWithNoChild(BusinessArea $businessArea): Collection
    {
        $tpls = $this->clientyConfigEmailTemplateRepository->findByBusinessAreaWithNoChild($businessArea);
        return $tpls;
    }


    public function findByBusinessAreaChild(BusinessAreaChild $businessAreaChild): Collection
    {
        $tpls = $this->clientyConfigEmailTemplateRepository->findByBusinessAreaChild($businessAreaChild);
        return $tpls;
    }


    public function findForAllBusinessArea(): Collection
    {
        $tpls = $this->clientyConfigEmailTemplateRepository->findForAllBusinessArea();
        return $tpls;
    }


    public function create(array $data, array $opts = []): ClientyConfigEmailTemplate
    {
        try {
            DB::beginTransaction();
            $emailTemplate = $this->clientyConfigEmailTemplateRepository->create($data);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $emailTemplate = $emailTemplate->fresh();
        return $emailTemplate;
    }


    public function update(ClientyConfigEmailTemplate $emailTemplate, $data): ClientyConfigEmailTemplate
    {
        try {
            DB::beginTransaction();
            $emailTemplate = $this->clientyConfigEmailTemplateRepository->update($emailTemplate, $data);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        
        return $emailTemplate->fresh();
    }


    public function delete(ClientyConfigEmailTemplate $emailTemplate)
    {
        return $this->clientyConfigEmailTemplateRepository->delete($emailTemplate);
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
