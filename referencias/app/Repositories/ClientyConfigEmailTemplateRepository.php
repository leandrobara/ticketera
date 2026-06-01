<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\BusinessArea;
use App\Models\BusinessAreaChild;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\Models\ClientyConfigEmailTemplate;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ClientyConfigEmailTemplateRepository
{

    public function list(Client $client, array $opts = []): Collection
    {
        $order = $opts['order'] ?? null;
        $filters = $opts['filters'] ?? [];
        $queryBuilder = ClientyConfigEmailTemplate::where('client_id', $client->id);
        foreach ($filters as $key => $value) {
            if (isset($filters[$key])) {
                if (is_array($value)) {
                    $queryBuilder->whereIn($key, $value);
                } elseif ($filters[$key] instanceof SQLFilterCriteria) {
                    $queryBuilder = $filters[$key]->filterSQLQuery($queryBuilder);
                } else {
                    $queryBuilder->where($key, $value);
                }
            }
        }
        if ($order) {
            if (is_a($order, SortCriteria::class)) {
                $queryBuilder = $order->applySort($queryBuilder);
            } else {
                $queryBuilder->orderByRaw($order);
            }
        }
        return $queryBuilder->get();
    }


    public function findByBusinessAreaWithNoChild(BusinessArea $businessArea): Collection
    {
        return ClientyConfigEmailTemplate::where('business_area_id', $businessArea->id)
            ->whereNull('business_area_child_id')
            ->get()
        ;
    }


    public function findByBusinessAreaChild(BusinessAreaChild $businessAreaChild): Collection
    {
        return ClientyConfigEmailTemplate::where('business_area_child_id', $businessAreaChild->id)
            ->where('business_area_id', $businessAreaChild->business_area_id)
            ->get()
        ;
    }


    public function findForAllBusinessArea(): Collection
    {
        return ClientyConfigEmailTemplate::whereNull('business_area_id')->get();
    }


    public function create(array $data): ClientyConfigEmailTemplate
    {
        $emailTemplate = new ClientyConfigEmailTemplate($data);
        $emailTemplate->saveOrFail();
        return $emailTemplate->fresh();
    }


    public function update(ClientyConfigEmailTemplate $emailTemplate, array $data): ClientyConfigEmailTemplate
    {
        $emailTemplate->fill($data);
        $emailTemplate->saveOrFail();
        return $emailTemplate->fresh();
    }


    public function delete(ClientyConfigEmailTemplate $emailTemplate): ClientyConfigEmailTemplate
    {
        $emailTemplate->delete();
        return $emailTemplate->fresh();
    }

}
