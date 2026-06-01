<?php

namespace App\Http\Requests\ClientSettings;

use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InClientSettingsReturnFields;


class UpdateClientSettingsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'register_sales_info' => ['sometimes', 'boolean'],
            'send_copy_email_type' => ['sometimes', 'boolean'],
            'enable_send_copy_email' => ['sometimes', 'boolean'],
            'send_copy_email_address' => ['sometimes', 'boolean'],
            'enable_google_gmail_api' => ['sometimes', 'boolean'],
            'see_notes_urls_as_links' => ['sometimes', 'boolean'],
            'enable_leads_call_button' => ['sometimes', 'boolean'],
            'enable_google_contacts_api' => ['sometimes', 'boolean'],
            'enable_leads_custom_fields' => ['sometimes', 'boolean'],
            'monthly_email_sending_quota' => ['sometimes', 'boolean'],
            'enable_new_lead_email_alert' => ['sometimes', 'boolean'],
            'enable_new_task_email_alert' => ['sometimes', 'boolean'],
            'enable_daily_task_email_alert' => ['sometimes', 'boolean'],
            'enable_new_lead_browser_alert' => ['sometimes', 'boolean'],
            'enable_new_task_browser_alert' => ['sometimes', 'boolean'],
            'show_leads_table_notes_column' => ['sometimes', 'boolean'],
            'massive_email_unsubscribe_text' => ['sometimes', 'string'],
            'register_sent_proposals_amount' => ['sometimes', 'boolean'],
            'default_tag_filter' => ['sometimes', Rule::in('and', 'or')],
            'see_acquisition_channel_as_label' => ['sometimes', 'boolean'],
            'see_disabled_email_reason_as_label' => ['sometimes', 'boolean'],
            'enable_proposals_manually_addition' => ['sometimes', 'boolean'],
            'show_leads_table_custom_fields_data' => ['sometimes', 'boolean'],
            'enable_task_user_change_email_alert' => ['sometimes', 'boolean'],
            'enable_task_user_change_browser_alert' => ['sometimes', 'boolean'],
            'enable_task_hour_reminder_email_alert' => ['sometimes', 'boolean'],
            'enable_new_task_whatsapp_message_alert' => ['sometimes', 'boolean'],
            'enable_new_lead_whatsapp_message_alert' => ['sometimes', 'boolean'],
            'google_gmail_api_scope' => ['sometimes', 'string', 'in:user,client'],
            'enable_task_hour_reminder_browser_alert' => ['sometimes', 'boolean'],
            'enable_daily_task_whatsapp_message_alert' => ['sometimes', 'boolean'],
            'enable_daily_email_for_each_expired_task' => ['sometimes', 'boolean'],
            'enable_daily_email_for_all_expired_tasks' => ['sometimes', 'boolean'],
            'enable_sent_proposal_reopened_email_alert' => ['sometimes', 'boolean'],
            'enable_users_type_permissions_restrictions' => ['sometimes', 'boolean'],
            'sent_proposal_reopened_email_alert_min_days' => ['sometimes', 'integer'],
            'enable_sent_proposal_reopened_browser_alert' => ['sometimes', 'boolean'],
            'sent_proposal_reopened_browser_alert_min_days' => ['sometimes', 'integer'],
            'enable_task_user_change_whatsapp_message_alert' => ['sometimes', 'boolean'],
            'enable_leads_call_button_auto_phone_formatting' => ['sometimes', 'boolean'],
            'enable_lead_proposal_interaction_browser_alert' => ['sometimes', 'boolean'],
            'google_contacts_api_sync_scope' => ['sometimes', 'string', 'in:user,client'],
            'enable_task_hour_reminder_whatsapp_message_alert' => ['sometimes', 'boolean'],
            'enable_daily_whatsapp_message_for_all_expired_tasks' => ['sometimes', 'boolean'],
            'enable_daily_whatsapp_message_for_each_expired_task' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InClientSettingsReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $enableDailyEmailForAllExpiredTasks = request()->input('enable_daily_email_for_all_expired_tasks');
                $enableDailyEmailForEachExpiredTask = request()->input('enable_daily_email_for_each_expired_task');
                if ($enableDailyEmailForAllExpiredTasks && $enableDailyEmailForEachExpiredTask) {
                    $validator->errors()->add(
                        'expired_tasks',
                        'can_not_allowed_expired_task_email_alerts_at_the_same_time'
                    );
                    return false;
                }

                $enableDailyWhatsAppMessageForAllExpiredTasks = request()
                    ->input('enable_daily_whatsapp_message_for_all_expired_tasks')
                ;
                $enableDailyWhatsAppMessageForEachExpiredTask = request()
                    ->input('enable_daily_whatsapp_message_for_each_expired_task')
                ;
                if ($enableDailyWhatsAppMessageForAllExpiredTasks && $enableDailyWhatsAppMessageForEachExpiredTask) {
                    $validator->errors()->add(
                        'expired_tasks',
                        'can_not_allowed_expired_task_whatsapp_message_alerts_at_the_same_time'
                    );
                    return false;
                }
            }


            

        });
    }


    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }

}
