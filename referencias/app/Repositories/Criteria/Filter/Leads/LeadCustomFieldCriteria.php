<?php

namespace App\Repositories\Criteria\Filter\Leads;

use Illuminate\Support\Collection;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LeadCustomFieldCriteria implements SQLFilterCriteria
{

    private $leadCustomFields;


    public function __construct(array $leadCustomFields)
    {
        $this->leadCustomFields = $leadCustomFields;
    }


    public function filterSQLQuery(object $builder): object
    {
        $conditions = $this->buildSearchConditions();

        foreach ($conditions as $field) {
            $builder->whereHas('leadCustomFieldsValues', function ($subQuery) use ($field) {
                $subQuery->where('lead_custom_field_id', $field['id'])->whereIn('value', $field['values']);
            });
        }

        return $builder;
    }


    private function buildSearchConditions(): array
    {
        $conditions = [];
        $customFields = $this->leadCustomFields;
        foreach ($customFields as $customField) {
            $values = array_map('trim', explode(',', $customField['value']));
            $filteredValues = array_filter($values, function ($value) {
                return $value !== null && $value !== '';
            });
            $conditions[] = [
                'id' => $customField['id'],
                'values' => $filteredValues
            ];
        }
        return $conditions;
    }
}
