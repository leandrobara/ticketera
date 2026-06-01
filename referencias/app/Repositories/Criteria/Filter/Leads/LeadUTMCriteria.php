<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LeadUTMCriteria implements SQLFilterCriteria
{

    protected $utms;


    public function __construct($utms)
    {
        $this->utms = $utms;
    }


    public function filterSQLQuery(object $builder): object
    {
        $utms = $this->utms;
        foreach ($utms as $utm) {
            $values = $this->buildUtmFilterValues($utm['value']);
            $builder->whereIn($utm['name'], $values);
        }

        return $builder;
    }


    private function buildUtmFilterValues(string $utmValues): array
    {
        $values = array_map('trim', explode(',', $utmValues));
        return array_filter($values, function ($value) {
            return $value !== null && $value !== '';
        });
    }

}
