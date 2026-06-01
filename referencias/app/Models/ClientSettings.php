<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\ClearRelationModelCache;


class ClientSettings extends Model
{

    use SoftDeletes, ClearRelationModelCache, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'ClientsSettings';

    const TASK_CREATE_TRIGGER_WEBHOOK_CODE = 'new_task';
    const LEAD_CREATE_TRIGGER_WEBHOOK_CODE = 'new_lead';
    const LEAD_SALE_TRIGGER_WEBHOOK_CODE = 'new_lead_sale';
    const LEAD_STATUS_CHANGE_TRIGGER_WEBHOOK_CODE = 'lead_status_change';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'enable_wapi' => 'boolean',
            'bonus_users' => 'integer',
            'acquired_users' => 'integer',
            'default_tag_filter' => 'string',
            'acquired_landings' => 'integer',
            'send_copy_email_type' => 'string',
            'register_sales_info' => 'boolean',
            'email_sending_blocked' => 'boolean',
            'google_gmail_api_scope' => 'string',
            'enable_send_copy_email' => 'boolean',
            'send_copy_email_address' => 'string',
            'enable_integration_api' => 'boolean',
            'show_non_payment_alert' => 'boolean',
            'enable_google_gmail_api' => 'boolean',
            'see_notes_urls_as_links' => 'boolean',
            'force_whatsapp_meta_api' => 'boolean',
            'lead_sale_trigger_webhook' => 'string',
            'enable_leads_call_button' => 'boolean',
            'enable_whatsapp_meta_api' => 'boolean',
            'daily_email_sending_quota' => 'integer',
            'lead_create_trigger_webhook' => 'string',
            'enable_leads_custom_fields' => 'boolean',
            'enable_google_contacts_api' => 'boolean',
            'task_create_trigger_webhook' => 'string',
            'monthly_email_sending_quota' => 'integer',
            'enable_new_lead_email_alert' => 'boolean',
            'enable_new_task_email_alert' => 'boolean',
            'enable_daily_task_email_alert' => 'boolean',
            'show_leads_table_notes_column' => 'boolean',
            'enable_new_task_browser_alert' => 'boolean',
            'enable_new_lead_browser_alert' => 'boolean',
            'enable_wapi_conversation_chat' => 'boolean',
            'massive_email_unsubscribe_text' => 'string',
            'google_contacts_api_sync_scope' => 'string',
            'lead_sale_trigger_make_webhook' => 'string',
            'acquired_extra_emails_sendings' => 'integer',
            'register_sent_proposals_amount' => 'boolean',
            'enable_leads_sales_bulk_upload' => 'boolean',
            'lead_create_trigger_make_webhook' => 'string',
            'task_create_trigger_make_webhook' => 'string',
            'lead_sale_trigger_zapier_webhook' => 'string',
            'see_acquisition_channel_as_label' => 'boolean',
            'enable_whatsapp_sender_extension' => 'boolean',
            'whatsapp_sender_quota_per_sending' => 'integer',
            'lead_status_change_trigger_webhook' => 'string',
            'lead_create_trigger_zapier_webhook' => 'string',
            'task_create_trigger_zapier_webhook' => 'string',
            'enable_whatsapp_sender_job_sending' => 'boolean',
            'see_disabled_email_reason_as_label' => 'boolean',
            'enable_proposals_manually_addition' => 'boolean',
            'enable_task_user_change_email_alert' => 'boolean',
            'show_leads_table_custom_fields_data' => 'boolean',
            'enable_task_user_change_browser_alert' => 'boolean',
            'enable_task_hour_reminder_email_alert' => 'boolean',
            'enable_whatsapp_auto_phone_formatting' => 'boolean',
            'enable_new_task_whatsapp_message_alert' => 'boolean',
            'lead_status_change_trigger_make_webhook' => 'string',
            'enable_new_lead_whatsapp_message_alert' => 'boolean',
            'enable_task_hour_reminder_browser_alert' => 'boolean',
            'enable_daily_task_whatsapp_message_alert' => 'boolean',
            'lead_status_change_trigger_zapier_webhook' => 'string',
            'enable_daily_email_for_each_expired_task' => 'boolean',
            'enable_daily_email_for_all_expired_tasks' => 'boolean',
            'enable_sent_proposal_reopened_email_alert' => 'boolean',
            'whatsapp_meta_api_conversations_permission' => 'string',
            'enable_users_type_permissions_restrictions' => 'boolean',
            'enable_sent_proposal_reopened_browser_alert' => 'boolean',
            'sent_proposal_reopened_email_alert_min_days' => 'integer',
            'whatsapp_sender_daily_sending_quota_per_user' => 'integer',
            'sent_proposal_reopened_browser_alert_min_days' => 'integer',
            'enable_task_user_change_whatsapp_message_alert' => 'boolean',
            'enable_leads_call_button_auto_phone_formatting' => 'boolean',
            'enable_lead_proposal_interaction_browser_alert' => 'boolean',
            'enable_task_hour_reminder_whatsapp_message_alert' => 'boolean',
            'enable_daily_whatsapp_message_for_all_expired_tasks' => 'boolean',
            'enable_daily_whatsapp_message_for_each_expired_task' => 'boolean',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
        ];

        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->hasOne(Client::class);
    }


    public function findLeadCreateWebhookTypeByEndpoint(string $webhookEndpoint): ?string
    {
        if ($webhookEndpoint == $this->lead_create_trigger_webhook) {
            return 'integration_api';
        }
        if ($webhookEndpoint == $this->lead_create_trigger_zapier_webhook) {
            return 'zapier';
        }
        if ($webhookEndpoint == $this->lead_create_trigger_make_webhook) {
            return 'make';
        }
        return null;
    }


    public function findLeadSaleWebhookTypeByEndpoint(string $webhookEndpoint): ?string
    {
        if ($webhookEndpoint == $this->lead_sale_trigger_webhook) {
            return 'integration_api';
        }
        if ($webhookEndpoint == $this->lead_sale_trigger_zapier_webhook) {
            return 'zapier';
        }
        if ($webhookEndpoint == $this->lead_sale_trigger_make_webhook) {
            return 'make';
        }
        return null;
    }


    public function findLeadStatusChangeWebhookTypeByEndpoint(string $webhookEndpoint): ?string
    {
        if ($webhookEndpoint == $this->lead_status_change_trigger_webhook) {
            return 'integration_api';
        }
        if ($webhookEndpoint == $this->lead_status_change_trigger_zapier_webhook) {
            return 'zapier';
        }
        if ($webhookEndpoint == $this->lead_status_change_trigger_make_webhook) {
            return 'make';
        }
        return null;
    }


    public function findTaskCreateWebhookTypeByEndpoint(string $webhookEndpoint): ?string
    {
        if ($webhookEndpoint == $this->task_create_trigger_webhook) {
            return 'integration_api';
        }
        if ($webhookEndpoint == $this->task_create_trigger_zapier_webhook) {
            return 'zapier';
        }
        if ($webhookEndpoint == $this->task_create_trigger_make_webhook) {
            return 'make';
        }
        return null;
    }

}
