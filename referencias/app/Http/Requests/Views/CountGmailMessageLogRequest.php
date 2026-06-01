<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;


class CountGmailMessageLogRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['sometimes', 'array'],
            'filters.user_id' => ['sometimes', 'nullable', 'integer'],
            'filters.send_date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.send_date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.type' => ['sometimes', 'string', 'nullable', 'in:only_sent,only_responses'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if ($val['filters']['send_date_end'] ?? null) {
            $date = (new DateTime($val['filters']['send_date_end']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['send_date_end'] = $date;
        }
        if ($val['filters']['send_date_start'] ?? null) {
            $date = (new DateTime($val['filters']['send_date_start']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['send_date_start'] = $date;
        }
        return $val;
    }

}
