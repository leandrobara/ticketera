<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TagORCriteria implements SQLFilterCriteria
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

        return $builder->where(function ($query) use ($hasNullValue) {
            if (count($this->tagIds)) {
                $query->whereHas('tags', function ($subQuery) {
                    $subQuery->whereIn('Tags.id', $this->tagIds);
                });
            }
            
            if ($hasNullValue) {
                $query->orWhere(function ($subQuery) {
                    $subQuery->whereDoesntHave('tags');
                });
            }
        });
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
