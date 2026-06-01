<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class ListProposalInfoRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['sometimes', 'array'],
            'filters.tag_id' => ['sometimes', 'nullable', 'array'],
            'filters.user_id' => ['sometimes', 'nullable', 'array'],
            'filters.search' => ['sometimes', 'nullable', 'string'],
            'filters.landing_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.send_date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.send_date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.acquisition_channel_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'page' => ['sometimes', 'int'],
            'limit' => ['sometimes', 'int'],
            'sort' => ['sometimes', 'string'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if ($val['filters']['send_date_end'] ?? null) {
            $date = (new DateTime($val['filters']['send_date_end']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['send_date_end'] = $date->format('Y-m-d\TH:i:sP');
        }
        if ($val['filters']['send_date_start'] ?? null) {
            $date = (new DateTime($val['filters']['send_date_start']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['send_date_start'] = $date->format('Y-m-d\TH:i:sP');
        }
        return $val;
    }

}
