<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;


class ListAutomationLogRequest extends APIBaseRequest
{

    public function rules()
    {
        $automationTypes = ['automation_new_lead', 'automation_proposal', 'automation_email_send', 'automation_task'];
        return [
            'page' => ['sometimes', 'int'],
            'limit' => ['sometimes', 'int'],
            'filters' => ['sometimes', 'array'],
            'filters.lead_id' => ['sometimes', 'int'],
            'filters.user_id' => ['sometimes', 'int'],
            'filters.is_fully_applied' => ['sometimes', 'boolean'],
            'filters.type' => ['sometimes', Rule::in($automationTypes)],
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
            'automationTask' => function ($q) {
                $q->withTrashed();
            },
            'automationNewLead' => function ($q) {
                $q->withTrashed();
            },
            'automationProposal' => function ($q) {
                $q->withTrashed();
            },
            'automationProposal.resendRule' => function ($q) {
                $q->withTrashed();
            },
            'automationProposal.interactionRule' => function ($q) {
                $q->withTrashed();
            },
            'automationProposal.modifyLeadAfterSendRule' => function ($q) {
                $q->withTrashed();
            },
            'automationProposal.resendRule.sendEmailTemplate' => function ($q) {
                $q->withTrashed();
            },
            'automationProposal.interactionRule.statusToAssign' => function ($q) {
                $q->withTrashed();
            },
            'automationProposal.modifyLeadAfterSendRule.statusToAssign' => function ($q) {
                $q->withTrashed();
            },
            'automationEmailSend' => function ($q) {
                $q->withTrashed();
            },
            'automationEmailSendStep' => function ($q) {
                $q->withTrashed();
            },
            'automationEmailSendStep.sendEmailTemplate' => function ($q) {
                $q->withTrashed();
            },
            'automationEmailSend.automationEmailSendSteps' => function ($q) {
                $q->withTrashed();
            },
        ];

        return $val;
    }

}
