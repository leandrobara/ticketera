<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\LeadCustomField;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;


class LeadCustomFieldRepository
{

    public function findAllByClient(Client $client): Collection
    {
        return LeadCustomField::where('client_id', $client->id)->orderBy('order')->orderBy('name')->get();
    }


    public function findAllWithDefaultValueByClient(Client $client): Collection
    {
        return LeadCustomField::where('client_id', $client->id)
            ->orderBy('name')
            ->orderBy('order')
            ->whereNotNull('default_value')
            ->get()
        ;
    }


    public function findMaxOrderByClient(Client $client)
    {
        return LeadCustomField::where('client_id', $client->id)->max('order');
    }


    public function create(array $data): LeadCustomField
    {
        $leadCustomField = new LeadCustomField($data);
        $leadCustomField->saveOrFail();
        return $leadCustomField->fresh();
    }


    public function update(LeadCustomField $leadCustomField, $data): LeadCustomField
    {
        $leadCustomField->fill($data);
        $leadCustomField->saveOrFail();
        return $leadCustomField->fresh();
    }


    public function delete(LeadCustomField $leadCustomField): LeadCustomField
    {
        $leadCustomField->delete();
        return $leadCustomField->fresh();
    }

}
