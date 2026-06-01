<?php

namespace App\Http\Requests\Views\Reports;

use App\Rules\IsArrayOfIntegers;
use App\Http\Requests\APIBaseRequest;


class TagCloseRateChartReportRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['sometimes', 'array'],
            'filters.tag_id' => ['sometimes', 'array'],
            'filters.tag_category_id' => ['sometimes', 'array'],
            'filters.tag_id.*' => ['sometimes', 'integer', 'nullable'],
            'filters.tag_category_id.*' => ['sometimes', 'integer', 'nullable'],
            'filters.user_id' => ['sometimes', 'nullable', new IsArrayOfIntegers()],
            'filters.acquisition_channel_id' => ['sometimes', 'nullable', new IsArrayOfIntegers()],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        return $validated;
    }

}
