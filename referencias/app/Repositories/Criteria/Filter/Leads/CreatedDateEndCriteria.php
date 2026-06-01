<?php

namespace App\Repositories\Criteria\Filter\Leads;

use DateTime;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;
use App\Repositories\Criteria\Filter\ElasticFilterCriteria;


// @DEPRECATED
class CreatedDateEndCriteria implements SQLFilterCriteria, ElasticFilterCriteria
{

    private $value;


    public function __construct($value)
    {
        $this->value = new DateTime($value);
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        return $builder->whereRaw(
            "lead_created_at <= '{$this->value->format('Y-m-d H:i:s')}'"
        );
    }

    // Deprecado: borrar
    public function filterElasticQuery(): array
    {
        $range = [
            'bool' => [
                'should' => [
                    'range' => [
                        'created_at' => [
                            'gte' => 0,
                            'lte' => $this->value->getTimestamp()
                        ]
                    ],
                    'range' => [
                        'lead_created_at' => [
                            'gte' => 0,
                            'lte' => $this->value->getTimestamp()
                        ]
                    ]
                ]
            ]
        ];
        return $range;
    }

}
