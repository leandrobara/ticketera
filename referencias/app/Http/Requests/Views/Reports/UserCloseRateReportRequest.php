<?php

namespace App\Http\Requests\Views\Reports;

use App\Rules\IsArrayOfIntegers;
use App\Http\Requests\APIBaseRequest;


class UserCloseRateReportRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['sometimes', 'array'],
            'close_date_type' => ['sometimes', 'string', 'in:leads,sales'],
            'filters.user_id' => ['sometimes', 'nullable', new IsArrayOfIntegers()],
            'filters.status_id' => ['sometimes', 'nullable', new IsArrayOfIntegers()],
            'breakdown' => ['sometimes', 'string', 'nullable', 'in:weekly,monthly,quarterly,yearly,historical'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        if (array_key_exists('user_id', $validated['filters'] ?? []) && $validated['filters']['user_id'] === null) {
            unset($validated['filters']['user_id']);
        }
        return $validated;
    }

}
