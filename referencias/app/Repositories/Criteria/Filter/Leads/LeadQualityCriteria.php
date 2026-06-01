<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LeadQualityCriteria implements SQLFilterCriteria
{

    protected $qualities;


    public function __construct($qualities)
    {
        $this->qualities = $qualities;
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        $isArray = is_array($this->qualities);
        if ($isArray && !count($this->qualities)) {
            return $builder;
        }

        if ($isArray) {
            return $this->buildWhereIn($builder, $this->qualities);
        }
        return $this->buildWhere($builder, $this->qualities);
    }


    private function buildWhereIn(object $builder, $qualities)
    {
        $qualities = array_map('intval', $qualities);
        $qualities = array_unique($qualities);

        $isOneItem = count($qualities) == 1;
        $hasNull = in_array(null, $qualities) || in_array(0, $qualities);

        if ($hasNull && $isOneItem) {
            return $builder->whereNull('quality');
        }

        if ($hasNull) {
            $qualitiesWithoutNull = array_filter($qualities);
            return $builder->where(function ($query) use ($qualitiesWithoutNull) {
                $query->whereIn('quality', $qualitiesWithoutNull)->orWhereNull('quality');
            });
        }

        return $builder->whereIn('quality', $qualities);
    }


    private function buildWhere(object $builder, $quality)
    {
        if (!$quality) {
            return $builder->whereNull('quality');
        }
        return $builder->where('quality', $quality);
    }

}
