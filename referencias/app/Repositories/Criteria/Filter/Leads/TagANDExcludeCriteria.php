<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TagANDExcludeCriteria implements SQLFilterCriteria
{

    public function __construct(private array $tagIds)
    {
        //
    }

    
    public function filterSQLQuery(object $builder): object
    {
        $tagIds = $this->tagIds;
        $tagIdsCount = count($tagIds);
        return $builder->whereDoesntHave('tags', function ($subQuery) use ($tagIds, $tagIdsCount) {
            $subQuery->whereIn('Tags.id', $tagIds);
            $subQuery->havingRaw('COUNT(DISTINCT Tags.id) = ?', [$tagIdsCount]);
        });
    }

}
