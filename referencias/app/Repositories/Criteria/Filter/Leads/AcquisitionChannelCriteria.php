<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class AcquisitionChannelCriteria implements SQLFilterCriteria
{

    public function __construct($value)
    {
        $this->value = $value;
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        if (is_array($this->value)) {
            return $this->buildWhereIn($builder);
        }
        return $this->buildWhere($builder);
    }


    private function buildWhereIn(object $builder)
    {
        $value = array_unique($this->value);
        if (in_array('null', $value)) {
            if (count($value) == 1) {
                $builder->where('acquisition_channel_id', null);
            } else {
                //unset null from values array
                unset($value[array_search('null', $value)]);
                $builder->where(function ($query) use ($value) {
                    $query->whereIn('acquisition_channel_id', $value)->orWhere('acquisition_channel_id', null);
                });
            }
        } else {
            $builder->whereIn('acquisition_channel_id', $value);
        }
        return $builder;
    }


    private function buildWhere(object $builder)
    {
        if ($this->value === 'null') {
            return $builder->where('acquisition_channel_id', null);
        }

        return $builder->where('acquisition_channel_id', $this->value);
    }

}
