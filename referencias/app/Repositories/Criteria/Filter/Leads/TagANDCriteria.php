<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TagANDCriteria implements SQLFilterCriteria
{

    private $tagIds;


    public function __construct($tagIds)
    {
        $this->tagIds = $tagIds;
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        if (is_array($this->tagIds)) {
            return $this->buildWhereIn($builder);
        }

        return $this->buildWhere($builder);
    }


    private function buildWhereIn(object $builder)
    {
        $hasNullValue = in_array('null', $this->tagIds);
        $this->tagIds = array_filter($this->tagIds, function ($tagId) {
            return $tagId != 'null';
        });

        if (count($this->tagIds)) {
            return $builder->whereHas('tags', function ($subQuery) {
                $subQuery
                    ->select('Leads_Tags.lead_id')
                    ->whereIn('Tags.id', $this->tagIds)
                    ->havingRaw('COUNT(*) = ' . count($this->tagIds))
                    ->groupBy('lead_id')
                ;
            });
        }

        if ($hasNullValue) {
            return $builder->whereDoesntHave('tags');
        }

        return $builder;
    }


    private function buildWhere(object $builder)
    {
        if ($this->value === 'null') {
            return $builder->whereDoesntHave('tags');
        }
        
        return $builder->whereHas('tags', function ($subQuery) {
            $subQuery->whereIn('Tags.id', $this->tagIds);
        });
    }

}
