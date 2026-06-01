<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\LeadCustomField;
use Illuminate\Support\Collection;
use App\Models\LeadCustomFieldValue;
use App\Exceptions\DatabaseException;


class LeadCustomFieldValueRepository
{
    
    public function create(array $data): LeadCustomFieldValue
    {
        $leadCustomFieldValue = new LeadCustomFieldValue($data);
        $leadCustomFieldValue->saveOrFail();
        return $leadCustomFieldValue->fresh();
    }


    public function update(LeadCustomFieldValue $leadCustomFieldValue, $value): LeadCustomFieldValue
    {
        $hash = LeadCustomFieldValue::buildHash($value);
        $leadCustomFieldValue->fill(['value' => $value, 'hash' => $hash]);
        $leadCustomFieldValue->saveOrFail();
        return $leadCustomFieldValue->fresh();
    }


    public function delete(LeadCustomFieldValue $leadCustomFieldValue): LeadCustomFieldValue
    {
        $leadCustomFieldValue->delete();
        return $leadCustomFieldValue->fresh();
    }


    public function deleteAllByLeadCustomField(LeadCustomField $leadCustomField)
    {
        return $leadCustomField->leadCustomFieldValues()->delete();
    }
}
