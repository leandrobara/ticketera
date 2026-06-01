<?php

namespace App\Repositories\Criteria\Filter\Tags;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class MultipleTagCategoryCriteria implements SQLFilterCriteria
{

    private $ids = [];


    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $queryBuilder): object
    {
        $queryBuilder->where(function ($q) {
            if (!$this->ids) {
                return $q;
            }

            $hasNull = in_array(null, $this->ids, true);
            $arrIds = array_filter($this->ids);
            if ($hasNull && $arrIds) {
                $q->whereIn('tag_category_id', $arrIds)->orWhereNull('tag_category_id');
                return $q;
            }

            if ($arrIds) {
                $q->whereIn('tag_category_id', $arrIds)->whereNotNull('tag_category_id');
                return $q;
            }

            if ($hasNull) {
                $q->whereNull('tag_category_id');
                return $q;
            }
        });
        return $queryBuilder;
    }

}
