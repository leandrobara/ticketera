<?php

namespace App\Http\Requests\Views;

use App\Models\News;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;


class ListClientNewsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['sometimes', 'array'],
            'page' => ['sometimes', 'nullable', 'int'],
            'limit' => ['sometimes', 'nullable', 'int'],
            'sort' => ['sometimes', Rule::in(['date_asc', 'date_desc'])],
            'filters.date_end' => ['sometimes', 'date_format:Y-m-d\TH:i:sP'],
            'filters.date_start' => ['sometimes', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if ($val['filters']['date_end'] ?? null) {
            $date = (new DateTime($val['filters']['date_end']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['date_end'] = $date->format('Y-m-d\TH:i:sP');
        }
        if ($val['filters']['date_start'] ?? null) {
            $date = (new DateTime($val['filters']['date_start']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['date_start'] = $date->format('Y-m-d\TH:i:sP');
        }
        if (!isset($val['sort'])) {
            $val['sort'] = 'date_desc';
        }
        return $val;
    }

}
