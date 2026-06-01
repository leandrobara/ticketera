<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InAutomationNewLeadReturnFields implements Rule
{
    private $allowedFields = [
        'id',
        'client_id',
        'add_tags_ids',
        'add_new_task',
        'add_new_note',
        'new_note_text',
        'assign_user_ids',
        'new_task_title',
        'assign_quality',
        'do_not_send_email',
        'grouped_email_body',
        'send_grouped_email',
        'status_id_to_assign',
        'triggering_lead_type',
        'new_task_description',
        'grouped_email_subject',
        'triggering_landing_ids',
        'new_task_days_to_expire',
        'auto_reply_send_min_hour',
        'auto_reply_send_max_hour',
        'trigger_if_email_repeatead',
        'trigger_if_phone_repeatead',
        'auto_reply_email_template_id',
        'auto_reply_do_not_send_out_of_hour',
        'auto_reply_ask_phone_email_template_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'client',
        'statusToAssign',
        'formFieldsToMatch',
        'utmParametersToMatch',
        'trackingParametersToMatch',
        'leadCustomFieldsMatch',
        'leadCustomFieldsMapping',
        'triggeringLandings',
        'tagsToAdd',
        'acquisitionChannelToAdd',
        'usersToAssign',
        'askPhoneEmailTemplate',
        'autoReplyEmailTemplate',
    ];


    private $customErrVal;


    public function passes($attribute, $value)
    {
        $ok = in_array($value, $this->allowedFields);
        if (!$ok) {
            $this->customErrVal = $value;
        }
        return $ok;
    }


    public function message()
    {
        return 'The field "' . $this->customErrVal . '" is not an AutomationNewLead field.';
    }
}
