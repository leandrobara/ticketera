<?php

namespace App\Http\Requests\Views\Reports;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class SalesFunnelReportListRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['required', 'array'],
            'filters.average_ticket' => ['required', 'string'],
            'filters.tag_id' => ['sometimes', 'nullable', 'array'],
            'filters.user_id'  => ['sometimes', 'nullable', 'array'],
            'filters.date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.acquisition_channel_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
        ];
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


    public function getFilterAverageTicket()
    {
        $val = parent::validated();
        return $val['filters']['average_ticket'] ?? null;
    }

}
