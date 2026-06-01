<?php

namespace App\Repositories\Criteria\Filter\LeadSales;

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
        $value = $this->value;
        return $builder->whereHas('lead', function (object $query) use ($value) {
            if (is_array($value)) {
                $query->whereIn('acquisition_channel_id', $value);
            } else {
                $query->where('acquisition_channel_id', $value);
            }
        });
    }

}
