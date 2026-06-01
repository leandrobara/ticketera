<?php

namespace App\Repositories\Criteria\Filter\LeadSales;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TagORCriteria implements SQLFilterCriteria
{

    private $tagIds;


    public function __construct($tagIds)
    {
        $this->tagIds = $tagIds;
    }

    public function filterSQLQuery(object $builder): object
    {
        if (is_array($this->tagIds) && count($this->tagIds)) {
            $tagIds = array_filter($this->tagIds, function ($tagId) {
                return $tagId != 'null';
            });

            return $builder->where(function ($query) use ($tagIds) {
                $query->whereHas('lead.tags', function ($subQuery) use ($tagIds) {
                    $subQuery->whereIn('Tags.id', $tagIds);
                });
            });
        }
    }
}
