<?php

namespace App\Http\Requests\Views\Reports;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class SalesFunnelReportAverageTicketAmountRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['sometimes', 'array'],
            'filters.date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
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

}
