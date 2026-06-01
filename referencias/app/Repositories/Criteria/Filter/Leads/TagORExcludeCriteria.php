<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TagORExcludeCriteria implements SQLFilterCriteria
{

    public function __construct(private array $tagIds)
    {
        //
    }


    public function filterSQLQuery(object $builder): object
    {
        $tagIds = $this->tagIds;
        return $builder->where(function ($query) use ($tagIds) {
            if ($this->tagIds) {
                $query->whereDoesntHave('tags', function ($subQuery) use ($tagIds) {
                    $subQuery->whereIn('Tags.id', $tagIds);
                });
            }
        });
    }

}
