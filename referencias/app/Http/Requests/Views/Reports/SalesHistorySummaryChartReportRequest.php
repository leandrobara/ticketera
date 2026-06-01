<?php

namespace App\Http\Requests\Views\Reports;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class SalesHistorySummaryChartReportRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'breakdown' => ['sometimes', 'in:weekly,monthly,quarterly,yearly,historical'],
            'filters' => ['sometimes', 'array'],
            'filters.tag_id' => ['sometimes', 'nullable', 'array'],
            'filters.search' => ['sometimes', 'nullable', 'string'],
            'filters.user_id'  => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.landing_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.sale_date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.sale_type' => ['sometimes', 'nullable', 'in:new_customer, old_customer'],
            'filters.sale_date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.acquisition_channel_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if (!isset($data['breakdown'])) {
            $data['breakdown'] = 'monthly';
        }
        if ($val['filters']['sale_date_end'] ?? null) {
            $date = (new DateTime($val['filters']['sale_date_end']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['sale_date_end'] = $date->format('Y-m-d\TH:i:sP');
        }
        if ($val['filters']['sale_date_start'] ?? null) {
            $date = (new DateTime($val['filters']['sale_date_start']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['sale_date_start'] = $date->format('Y-m-d\TH:i:sP');
        }
        return $val;
    }

}
