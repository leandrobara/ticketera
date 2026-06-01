<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use Illuminate\Validation\Rule;
use App\Rules\IsTaskStatusOrArray;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class ListTaskRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'page' => ['sometimes', 'int'],
            'limit' => ['sometimes', 'int'],
            'sort' => ['sometimes', Rule::in(['limit_date_asc', 'limit_date_desc'])],
            'filters' => ['sometimes', 'array'],
            'filters.is_important' => ['sometimes', 'boolean'],
            'filters.search' => ['sometimes', 'nullable', 'string'],
            'filters.user_id' => ['sometimes', 'nullable', 'array'],
            'filters.status' => ['sometimes', 'nullable', new IsTaskStatusOrArray()],
            'filters.limit_date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.limit_date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        
        if ($val['filters']['limit_date_end'] ?? null) {
            $date = (new DateTime($val['filters']['limit_date_end']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['limit_date_end'] = $date->format('Y-m-d\TH:i:sP');
        }
        
        if ($val['filters']['limit_date_start'] ?? null) {
            $date = (new DateTime($val['filters']['limit_date_start']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['limit_date_start'] = $date->format('Y-m-d\TH:i:sP');
        }

        if (!isset($val['sort'])) {
            $val['sort'] = 'limit_date_desc';
        }

        //To eager load relations
        $val['with'] = [
            'user',
            'client',
            'lead' => function ($query) {
                $query->with([
                    'user',
                    'status',
                    'mainLeadContact',
                    'mainLeadContact.leadContactEmails',
                    'mainLeadContact.leadContactPhones',
                ])->withTrashed();
            },
        ];

        return $val;
    }

}
