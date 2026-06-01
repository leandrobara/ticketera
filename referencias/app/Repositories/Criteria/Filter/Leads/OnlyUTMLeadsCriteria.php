<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;
use App\Repositories\Criteria\Filter\ElasticFilterCriteria;


// @DEPRECATED
class OnlyUTMLeadsCriteria implements SQLFilterCriteria, ElasticFilterCriteria
{

    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        // return $builder->where('utm_keywords', '!=', null);
        return $builder->where(function ($q) {
            $q->orWhereNotNull(['utm_source', 'utm_content', 'utm_keywords']);
        });
    }


    public function filterElasticQuery(): array
    {
        return [
            'bool' => [
                'must' => [
                    'exists' => [
                        'field' => 'utm_keywords'
                    ]
                ]
            ]
        ];
    }

}
