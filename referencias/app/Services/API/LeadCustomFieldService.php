<?php

namespace App\Services\API;

use Throwable;
use Exception;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadCustomField;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\LeadCustomFieldRepository;
use App\Services\API\Dispatchers\SearchLeadEventsDispatcherService;


class LeadCustomFieldService
{

    use GetClientFromRequest;

    private $leadCustomFieldRepository;
    private $leadCustomFieldValueService;
    private $searchLeadEventsDispatcherService;


    public function __construct(
        LeadCustomFieldRepository $leadCustomFieldRepository,
        LeadCustomFieldValueService $leadCustomFieldValueService,
        SearchLeadEventsDispatcherService $searchLeadEventsDispatcherService
    ) {
        $this->leadCustomFieldRepository = $leadCustomFieldRepository;
        $this->leadCustomFieldValueService = $leadCustomFieldValueService;
        $this->searchLeadEventsDispatcherService = $searchLeadEventsDispatcherService;
    }


    public function findAllByClient(?Client $client = null): Collection
    {
        $client = $client ?? $this->getClient();
        return $this->leadCustomFieldRepository->findAllByClient($client);
    }


    public function findAllWithDefaultValueByClient(?Client $client = null): Collection
    {
        $client = $client ?? $this->getClient();
        return $this->leadCustomFieldRepository->findAllWithDefaultValueByClient($client);
    }


    public function setValue(
        Lead $lead,
        LeadCustomField $leadCustomField,
        ?string $valueStr,
        ?Client $client = null
    ): LeadCustomField {
        $client = $client ?? $this->getClient();
        $leadCustomFieldValue = $leadCustomField->getLeadCustomFieldValueByLead($lead);
        if (!$leadCustomFieldValue && $valueStr != null) {
            $this->leadCustomFieldValueService->create($lead, $leadCustomField, $valueStr, $client);
        } elseif ($leadCustomFieldValue && $valueStr == null) {
            $this->leadCustomFieldValueService->delete($leadCustomFieldValue);
        } elseif ($leadCustomFieldValue  && $valueStr != null) {
            $currentValue = $leadCustomFieldValue->value;
            $isDifferent = ($valueStr != $currentValue);
            if ($isDifferent) {
                $this->leadCustomFieldValueService->update($leadCustomFieldValue, $valueStr);
            }
        }
        
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
        return $leadCustomField;
    }


    public function changeOrder(LeadCustomField $leadCustomField, string $direction): LeadCustomField
    {
        if (!in_array($direction, ['up', 'down'])) {
            throw new Exception('Direction is not allowed');
        }

        $oldOrder = $leadCustomField->order;
        $newOrder = ($direction == 'up') ? $oldOrder - 1 : $oldOrder + 1;
        
        $leadCustomFields = $this->leadCustomFieldRepository->findAllByClient($this->getClient());
        $leadCustomFieldToUpdate = $leadCustomFields->filter(function ($leadCustomField) use ($newOrder) {
            return $leadCustomField->order == $newOrder;
        })->first();

        try {
            DB::beginTransaction();
            $this->update($leadCustomField, ['order' => $newOrder]);
            $this->update($leadCustomFieldToUpdate, ['order' => $oldOrder]);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $leadCustomField;
    }


    public function create($data): LeadCustomField
    {
        $client = $data['client'] ?? $this->getClient();
        $data['client_id'] = $client->id;

        $lastOrder = $this->leadCustomFieldRepository->findMaxOrderByClient($client);
        $data['order'] = $lastOrder === null ? 0 : $lastOrder + 1;

        return $this->leadCustomFieldRepository->create($data);
    }


    public function createDefaultValues(Lead $lead): Collection
    {
        $client = $lead->client;
        $leadCustomFields = $this->findAllWithDefaultValueByClient($client);
        
        foreach ($leadCustomFields as $leadCustomField) {
            $defaultValue = $leadCustomField->default_value;
            if ($defaultValue) {
                $this->setValue($lead, $leadCustomField, $defaultValue, $client);
            }
        }
        return $leadCustomFields;
    }


    public function update(LeadCustomField $leadCustomField, $data): LeadCustomField
    {
        return $this->leadCustomFieldRepository->update($leadCustomField, $data);
    }


    public function delete(LeadCustomField $leadCustomField): LeadCustomField
    {
        try {
            DB::beginTransaction();
            $this->leadCustomFieldValueService->deleteAllByLeadCustomField($leadCustomField);
            $this->leadCustomFieldRepository->delete($leadCustomField);
            $this->reOrderAll($this->getClient());
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $leadCustomField->fresh();
    }


    protected function reOrderAll(Client $client): void
    {
        $order = 0;
        $leadCustomFields = $this->leadCustomFieldRepository->findAllByClient($client);
        foreach ($leadCustomFields as $leadCustomField) {
            $leadCustomField->order = $order;
            $leadCustomField->saveOrFail();
            $order++;
        }
    }

}
