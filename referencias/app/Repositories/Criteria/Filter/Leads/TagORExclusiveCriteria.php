<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TagORExclusiveCriteria implements SQLFilterCriteria
{

    public function __construct(private array|int|string $tagIds)
    {
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        if (!is_array($this->tagIds)) {
            $this->tagIds = [$this->tagIds];
        }
        
        $hasNullValue = in_array('null', $this->tagIds);
        $this->tagIds = array_filter($this->tagIds, function ($tagId) {
            return $tagId != 'null';
        });

        return $builder->where(function ($query) use ($hasNullValue) {
            if ($this->tagIds) {
                $query
                    ->whereHas('tags', function ($subQuery) {
                        $subQuery->whereIn('Tags.id', $this->tagIds);
                    })
                    ->whereDoesntHave('tags', function ($subQuery) {
                        $subQuery->whereNotIn('Tags.id', $this->tagIds);
                    });
                ;
            }
            if ($hasNullValue) {
                $query->orWhere(function ($subQuery) {
                    $subQuery->whereDoesntHave('tags');
                });
            }
        });
    }

}
