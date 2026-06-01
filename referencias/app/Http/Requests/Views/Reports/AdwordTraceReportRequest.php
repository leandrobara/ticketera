<?php

namespace App\Http\Requests\Views\Reports;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class AdwordTraceReportRequest extends APIBaseRequest
{
    
    public function rules()
    {
        return [
            'page' => ['sometimes', 'int'],
            'limit' => ['sometimes', 'int'],
            'filters' => ['sometimes', 'array'],
            'filters.user_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.status_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.created_date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.created_date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.search' => ['sometimes', 'nullable', 'string'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        
        if ($val['filters']['created_date_start'] ?? null) {
            $date = (new DateTime($val['filters']['created_date_start']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['created_date_start'] = $date->format('Y-m-d\TH:i:sP');
        }
        if ($val['filters']['created_date_end'] ?? null) {
            $date = (new DateTime($val['filters']['created_date_end']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['created_date_end'] = $date->format('Y-m-d\TH:i:sP');
        }

        return $val;
    }

}
