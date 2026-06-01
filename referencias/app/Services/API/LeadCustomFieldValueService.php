<?php

namespace App\Services\API;

use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadCustomField;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\LeadCustomFieldValue;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\LeadCustomFieldValueRepository;


class LeadCustomFieldValueService
{

    use GetClientFromRequest;

    private $leadCustomFieldValueRepository;


    public function __construct(LeadCustomFieldValueRepository $leadCustomFieldValueRepository)
    {
        $this->leadCustomFieldValueRepository = $leadCustomFieldValueRepository;
    }


    public function create(
        Lead $lead,
        LeadCustomField $leadCustomField,
        string $value,
        ?Client $client = null
    ): LeadCustomFieldValue {
        $client = $client ?? $this->getClient()->id;

        $data['value'] = $value;
        $data['lead_id'] = $lead->id;
        $data['client_id'] = $client->id;
        $data['lead_custom_field_id'] = $leadCustomField->id;
        $data['hash'] = LeadCustomFieldValue::buildHash($value);

        return $this->leadCustomFieldValueRepository->create($data);
    }


    public function update(LeadCustomFieldValue $leadCustomFieldValue, string $value): LeadCustomFieldValue
    {
        return $this->leadCustomFieldValueRepository->update($leadCustomFieldValue, $value);
    }


    public function delete(LeadCustomFieldValue $leadCustomFieldValue): LeadCustomFieldValue
    {
        return $this->leadCustomFieldValueRepository->delete($leadCustomFieldValue);
    }


    public function deleteAllByLeadCustomField(LeadCustomField $leadCustomField)
    {
        return $this->leadCustomFieldValueRepository->deleteAllByLeadCustomField($leadCustomField);
    }

}
