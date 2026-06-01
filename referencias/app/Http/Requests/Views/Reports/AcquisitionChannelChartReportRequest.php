<?php

namespace App\Http\Requests\Views\Reports;

use App\Rules\IsArrayOfIntegers;
use App\Http\Requests\APIBaseRequest;


class AcquisitionChannelChartReportRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'type' => [
                'sometimes',
                'nullable',
                'in:sales_per_channel,proposals_per_channel,quality_leads_per_channel'
            ],
            'breakdown' => [
                'sometimes',
                'nullable',
                'in:weekly,monthly,quarterly,yearly,historical'
            ],
            'filters' => ['sometimes', 'array'],
            'filters.user_id' => ['sometimes', 'nullable', new IsArrayOfIntegers()],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        if (!isset($validated['type'])) {
            $validated['type'] = 'sales_per_channel';
        }
        if (!isset($validated['breakdown'])) {
            $validated['breakdown'] = 'monthly';
        }
        return $validated;
    }

}
