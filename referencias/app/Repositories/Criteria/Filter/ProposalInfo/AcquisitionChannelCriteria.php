<?php

namespace App\Repositories\Criteria\Filter\ProposalInfo;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class AcquisitionChannelCriteria implements SQLFilterCriteria
{

    public function __construct($value)
    {
        if (!is_array($value) && $value) {
            $value = collect($value);
        }
        $this->value = $value;
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        $acquisitionChannelIds = $this->value;
        if (!$acquisitionChannelIds) {
            return $builder;
        }
        return $builder->whereHas('lead', function (object $query) use ($acquisitionChannelIds) {
            $query->whereIn('acquisition_channel_id', $acquisitionChannelIds);
        });
    }

}
