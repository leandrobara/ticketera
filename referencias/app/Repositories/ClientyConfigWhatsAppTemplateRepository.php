<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\BusinessArea;
use App\Models\BusinessAreaChild;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\Models\ClientyConfigWhatsAppTemplate;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ClientyConfigWhatsAppTemplateRepository
{

    public function list(Client $client, array $opts = []): Collection
    {
        $order = $opts['order'] ?? null;
        $filters = $opts['filters'] ?? [];
        $queryBuilder = ClientyConfigWhatsAppTemplate::where('client_id', $client->id);
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
        return ClientyConfigWhatsAppTemplate::where('business_area_id', $businessArea->id)
            ->whereNull('business_area_child_id')
            ->get()
        ;
    }


    public function findByBusinessAreaChild(BusinessAreaChild $businessAreaChild): Collection
    {
        return ClientyConfigWhatsAppTemplate::where('business_area_child_id', $businessAreaChild->id)
            ->where('business_area_id', $businessAreaChild->business_area_id)
            ->get()
        ;
    }

    
    public function findForAllBusinessArea(): Collection
    {
        return ClientyConfigWhatsAppTemplate::whereNull('business_area_id')->get();
    }


    public function create(array $data): ClientyConfigWhatsAppTemplate
    {
        $whatsAppTemplate = new ClientyConfigWhatsAppTemplate($data);
        $whatsAppTemplate->saveOrFail();
        return $whatsAppTemplate->fresh();
    }


    public function update(ClientyConfigWhatsAppTemplate $whatsAppTemplate, array $data): ClientyConfigWhatsAppTemplate
    {
        $whatsAppTemplate->fill($data);
        $whatsAppTemplate->saveOrFail();
        return $whatsAppTemplate->fresh();
    }


    public function delete(ClientyConfigWhatsAppTemplate $whatsAppTemplate): ClientyConfigWhatsAppTemplate
    {
        $whatsAppTemplate->delete();
        return $whatsAppTemplate->fresh();
    }

}
