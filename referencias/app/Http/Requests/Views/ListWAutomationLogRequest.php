<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;


class ListWAutomationLogRequest extends APIBaseRequest
{

    public function rules()
    {
        $automationTypes = ['wautomation_after_send', 'wautomation_proposal', 'wautomation_sequence'];
        return [
            'page' => ['sometimes', 'int'],
            'limit' => ['sometimes', 'int'],
            'sort' => ['sometimes', Rule::in(['date_asc', 'date_desc'])],
            'filters' => ['sometimes', 'array'],
            'filters.user_id' => ['sometimes', 'int'],
            'filters.lead_id' => ['sometimes', 'int'],
            'filters.is_fully_applied' => ['sometimes', 'boolean'],
            'filters.type' => ['sometimes', Rule::in($automationTypes)],
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

        //To eager load relations
        $val['with'] = [
            'lead' => function ($q) {
                $q->withTrashed();
            },
            'lead.user' => function ($q) {
                $q->withTrashed();
            },
            'lead.mainLeadContact' => function ($q) {
                $q->withTrashed();
            },
            'lead.mainLeadContact.leadContactEmails' => function ($q) {
                $q->withTrashed();
            },
            'lead.mainLeadContact.leadContactPhones' => function ($q) {
                $q->withTrashed();
            },
            'whatsAppSending' => function ($q) {
                $q->withTrashed();
            },
            'whatsAppSendingMessage' => function ($q) {
                $q->withTrashed();
            },
            'wAutomationAfterSend' => function ($q) {
                $q->withTrashed();
            },
            'wAutomationProposal' => function ($q) {
                $q->withTrashed();
            },
            'wAutomationProposal.resendRule' => function ($q) {
                $q->withTrashed();
            },
            'wAutomationProposal.modifyLeadAfterSendRule' => function ($q) {
                $q->withTrashed();
            },
            'wAutomationProposal.resendRule.sendWhatsAppTemplate' => function ($q) {
                $q->withTrashed();
            },
            'wAutomationProposal.modifyLeadAfterSendRule.statusToAssign' => function ($q) {
                $q->withTrashed();
            },
        ];
        return $val;
    }

}
