<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InClientSettingsReturnFields implements Rule
{

    private $customErrVal;
    private $allowedFields = [
        'client',
        'default_tag_filter',
        'register_sales_info',
        'send_copy_email_type',
        'enable_send_copy_email',
        'send_copy_email_address',
        'enable_google_contacts_api',
        'enable_leads_custom_fields',
        'enable_new_lead_email_alert',
        'enable_new_task_email_alert',
        'monthly_email_sending_quota',
        'enable_daily_task_email_alert',
        'enable_new_lead_browser_alert',
        'enable_new_task_browser_alert',
        'google_contacts_api_sync_scope',
        'register_sent_proposals_amount',
        'massive_email_unsubscribe_text',
        'see_acquisition_channel_as_label',
        'enable_proposals_manually_addition',
        'enable_task_user_change_email_alert',
        'enable_task_user_change_browser_alert',
        'enable_task_hour_reminder_email_alert',
        'enable_new_task_whatsapp_message_alert',
        'enable_task_hour_reminder_browser_alert',
        'enable_task_user_change_whatsapp_message_alert',
        'enable_lead_proposal_interaction_browser_alert',
    ];


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
        return 'The field "' . $this->customErrVal . '" is not an ClientSettings field.';
    }

}
