<?php

namespace App\Http\Requests\Automations;

use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;


class AutomationFlowChartRequest extends APIBaseRequest
{

    public function rules()
    {
        $flowChartTypeAllowed = [
            'automation_task',
            'automation_new_lead',
            'automation_proposal',
            'wautomation_proposal',
            'automation_email_send',
            'wautomation_sequence',
            'automation_task_related_wautomation_sequence',
            'automation_task_related_automation_email_send',
            'automation_task_related_email_send_wautomation_sequence',
            'automation_new_lead_related_wautomation_sequence',
            'automation_new_lead_related_automation_email_send',
            'automation_new_lead_related_email_send_wautomation_sequence',
        ];
        return [
            'filters' => ['required', 'array'],
            'filters.flowChartType' => ['required', 'string', Rule::in($flowChartTypeAllowed)],
        ];
    }


    public function getFlowChartType(): string
    {
        return $this->validated()['filters']['flowChartType'];
    }

}
