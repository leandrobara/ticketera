<?php

namespace App\Http\Requests\Views\Reports;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;


class ListWapBotConversationRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'sort' => ['sometimes', 'string'],
            'page' => ['sometimes', 'nullable', 'int'],
            'limit' => ['sometimes', 'nullable', 'int'],
            'filters.type' => ['sometimes', 'nullable', 'string'],
            'filters.leadId' => ['sometimes', 'nullable', 'integer'],
            'filters.hasLead' => ['sometimes', 'nullable', 'boolean'],
            'filters.isEnded' => ['sometimes', 'nullable', 'boolean'],
            'filters.dateEnd' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.dateStart' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.customerPhoneNumber' => ['sometimes', 'nullable', 'string'],
            'filters.botMetaPhoneNumberId' => ['sometimes', 'nullable', 'array'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if ($val['filters']['dateEnd'] ?? null) {
            $date = (new DateTime($val['filters']['dateEnd']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['dateEnd'] = $date;
        }
        if ($val['filters']['dateStart'] ?? null) {
            $date = (new DateTime($val['filters']['dateStart']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['dateStart'] = $date;
        }
        $val['with'] = [];

        if (!isset($val['sort'])) {
            $val['sort'] = 'date_desc';
        }

        $val['withTrashed'] = true;
        return $val;
    }

}

