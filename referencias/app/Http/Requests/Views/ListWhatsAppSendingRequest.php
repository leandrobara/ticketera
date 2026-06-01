<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;


class ListWhatsAppSendingRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'sort' => ['sometimes', 'string'],
            'page' => ['sometimes', 'nullable', 'int'],
            'limit' => ['sometimes', 'nullable', 'int'],
            'filters.user_id' => ['sometimes', 'nullable', 'array'],
            'filters.lead_id' => ['sometimes', 'nullable', 'integer'],
            'filters.send_status' => ['sometimes', 'nullable', 'string'],
            'filters.is_massive' => ['sometimes', 'nullable', 'boolean'],
            'filters.is_automation' => ['sometimes', 'nullable', 'boolean'],
            'filters.send_date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.send_date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
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
            'user',
            'whatsAppSendingMessages',
            'whatsAppSendingMessageText',
            'whatsAppSendingMessages.wAutomationLog',
            'whatsAppSendingMessages.wAutomationLog.wAutomationSequence' => function ($q) {
                $q->withTrashed();
            },
        ];

        if (!isset($val['sort'])) {
            $val['sort'] = 'date_desc';
        }
        
        return $val;
    }

}
