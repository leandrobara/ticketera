<?php

namespace App\Http\Requests\Views\Reports;

use DateTime;
use DateTimeZone;
use App\Rules\IsArrayOfIntegers;
use App\Http\Requests\APIBaseRequest;


class UsersActivityReportRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['sometimes', 'array'],
            'filters.user_id' => ['sometimes', 'nullable', 'array'],
            'filters.date_end' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'filters.date_start' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'filters.user_type' => ['sometimes', 'string', 'in:all,enabled,disabled'],
        ];
    }



    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $loginUser = request()->input('user');
                if ($loginUser->type != 'admin') {
                    $validator->errors()->add('user', 'current_user_is_not_admin');
                    return false;
                }
            });
        }
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if ($val['filters']['date_end'] ?? null) {
            $date = (new DateTime($val['filters']['date_end']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['date_end'] = $date;
        }
        if ($val['filters']['date_start'] ?? null) {
            $date = (new DateTime($val['filters']['date_start']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['date_start'] = $date;
        }
        return $val;
    }
}
