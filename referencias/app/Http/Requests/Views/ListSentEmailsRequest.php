<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;


class ListSentEmailsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['sometimes', 'array'],
            'filters.opened_only' => ['sometimes', 'boolean'],
            'filters.search' => ['sometimes', 'string', 'nullable'],
            'filters.user_id' => ['sometimes', 'nullable', 'array'],
            'filters.is_opened' => ['sometimes', 'nullable', 'boolean'],
            'filters.send_date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.send_date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
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
        
        $val['with'] = [
            'user' => function ($q) {
                $q->withTrashed();
            },
            'lead' => function ($q) {
                $q->withTrashed();
            },
            'lead.status' => function ($q) {
                $q->withTrashed();
            },
            'lead.acquisitionChannel' => function ($q) {
                $q->withTrashed();
            },
            'leadContactEmail' => function ($q) {
                $q->withTrashed();
            },
            'leadContactEmail.leadContact' => function ($q) {
                $q->withTrashed();
            },
        ];
        return $val;
    }

}
