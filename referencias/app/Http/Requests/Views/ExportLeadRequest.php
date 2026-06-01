<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;
use App\Rules\IsRequiredNullableIntegerOrArray;


class ExportLeadRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'page' => ['required', 'int'],
            'limit' => ['required', 'int'],
            'filters' => ['sometimes', 'array'],
            'userIp' => ['sometimes', 'nullable', 'string'],
            'filters.landing_id' => ['sometimes', new IsRequiredNullableIntegerOrArray()],
            'filters.tag_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.user_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.status_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.tag_filter_type' => ['sometimes', Rule::in(['or', 'and', 'or_exclusive'])],
            'filters.created_date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.created_date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.acquisition_channel_id' => ['sometimes', new IsRequiredNullableIntegerOrArray()],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if ($val['filters']['created_date_end'] ?? null) {
            $date = (new DateTime($val['filters']['created_date_end']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['created_date_end'] = $date->format('Y-m-d\TH:i:sP');
        }
        if ($val['filters']['created_date_start'] ?? null) {
            $date = (new DateTime($val['filters']['created_date_start']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['created_date_start'] = $date->format('Y-m-d\TH:i:sP');
        }

        //To eager load relations
        $val['with'] = [
            'tags',
            'user',
            'notes',
            'status',
            'landing',
            'mainLeadContact',
            'acquisitionChannel',
            'leadCustomFieldsValues',
            'client.leadsCustomFields',
            'mainLeadContact.leadContactEmailsUnordered',
            'mainLeadContact.leadContactPhonesUnordered',
        ];

        return $val;
    }

}
