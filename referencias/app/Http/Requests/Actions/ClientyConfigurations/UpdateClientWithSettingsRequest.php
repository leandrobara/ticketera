<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use App\Http\Requests\APIBaseRequest;


class UpdateClientWithSettingsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'timezone' => ['sometimes', 'string'],
            'clientSettings' => ['present', 'array'],
            'clientSettings.enable_wapi' => ['sometimes', 'boolean'],
            'clientSettings.bonus_users' => ['sometimes', 'integer', 'min:0'],
            'clientSettings.enable_integration_api' => ['sometimes', 'boolean'],
            'clientSettings.show_non_payment_alert' => ['sometimes', 'boolean'],
            'clientSettings.acquired_users' => ['sometimes', 'integer', 'min:0'],
            'clientSettings.force_whatsapp_meta_api' => ['sometimes', 'boolean'],
            'clientSettings.whatsapp_meta_api_conversations_permission' => [
                'sometimes', 'nullable', 'string', 'in:all,none,owner_only,leads_only,owner_leads_only'
            ],
            'clientSettings.enable_whatsapp_meta_api' => ['sometimes', 'boolean'],
            'clientSettings.acquired_landings' => ['sometimes', 'integer', 'min:0'],
            'clientSettings.enable_wapi_conversation_chat' => ['sometimes', 'boolean'],
            'clientSettings.enable_leads_sales_bulk_upload' => ['sometimes', 'boolean'],
            'clientSettings.enable_whatsapp_sender_extension' => ['sometimes', 'boolean'],
            'clientSettings.enable_whatsapp_sender_job_sending' => ['sometimes', 'boolean'],
            'clientSettings.enable_whatsapp_auto_phone_formatting' => ['sometimes', 'boolean'],
            'clientSettings.daily_email_sending_quota' => ['sometimes', 'integer', 'min:0'],
            'clientSettings.monthly_email_sending_quota' => ['sometimes', 'integer', 'min:0'],
            'clientSettings.lead_sale_trigger_webhook' => ['sometimes', 'nullable', 'string'],
            'clientSettings.task_create_trigger_webhook' => ['sometimes', 'nullable', 'string'],
            'clientSettings.lead_create_trigger_webhook' => ['sometimes', 'nullable', 'string'],
            'clientSettings.acquired_extra_emails_sendings' => ['sometimes', 'integer', 'min:0'],
            'clientSettings.lead_status_change_trigger_webhook' => ['sometimes', 'nullable', 'string'],
            'clientSettings.whatsapp_sender_daily_sending_quota_per_user' => ['sometimes', 'integer', 'min:0'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                
                $client = request()->input('client');
                $clientSettings = request()->input('clientSettings');
                $clientyClientId = (int) config('app.clienty.client_id');
                $WAPIIsEnabled = $clientSettings['enable_wapi'] ?? false;
                $isClientyAdminUser = request()->user->is_clienty_admin_user;
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;
                $integrationApiIsEnabled = $clientSettings['enable_integration_api'] ?? null;
                $leadSaleTriggerWebhook = $clientSettings['lead_sale_trigger_webhook'] ?? null;
                $taskCreateTriggerWebhook = $clientSettings['task_create_trigger_webhook'] ?? null;
                $leadCreateTriggerWebhook = $clientSettings['lead_create_trigger_webhook'] ?? null;
                $WAPIConversationChatIsEnabled = $clientSettings['enable_wapi_conversation_chat'] ?? false;
                $leadStatusChangeTriggerWebhook = $clientSettings['lead_status_change_trigger_webhook'] ?? null;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                
                if (!$isSuperUser && !$isClientyAdminUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }

                if (!$WAPIIsEnabled) {
                    if ($WAPIConversationChatIsEnabled) {
                        $validator->errors()->add('enable_wapi_conversation_chat', 'wapi_is_not_enabled');
                        return false;
                    }
                }

                if (!$integrationApiIsEnabled) {
                    if ($leadCreateTriggerWebhook) {
                        $validator->errors()->add('lead_create_trigger_webhook', 'integration_api_is_not_enabled');
                        return false;
                    }

                    if ($leadSaleTriggerWebhook) {
                        $validator->errors()->add('lead_sale_trigger_webhook', 'integration_api_is_not_enabled');
                        return false;
                    }

                    if ($leadStatusChangeTriggerWebhook) {
                        $validator->errors()->add(
                            'lead_status_change_trigger_webhook', 'integration_api_is_not_enabled'
                        );
                        return false;
                    }

                    if ($taskCreateTriggerWebhook) {
                        $validator->errors()->add('task_create_trigger_webhook', 'integration_api_is_not_enabled');
                        return false;
                    }
                }

                if ($integrationApiIsEnabled) {
                    if ($leadCreateTriggerWebhook && !$this->isValidURL($leadCreateTriggerWebhook)) {
                        $validator->errors()->add(
                            'lead_create_trigger_webhook', 'lead_sale_trigger_webhook_invalid_url'
                        );
                        return false;
                    }

                    if ($leadSaleTriggerWebhook && !$this->isValidURL($leadSaleTriggerWebhook)) {
                        $validator->errors()->add(
                            'lead_sale_trigger_webhook', 'lead_sale_trigger_webhook_invalid_url'
                        );
                        return false;
                    }

                    if ($leadStatusChangeTriggerWebhook && !$this->isValidURL($leadStatusChangeTriggerWebhook)) {
                        $validator->errors()->add(
                            'lead_status_change_trigger_webhook', 'lead_sale_trigger_webhook_invalid_url'
                        );
                        return false;
                    }

                    if ($taskCreateTriggerWebhook && !$this->isValidURL($taskCreateTriggerWebhook)) {
                        $validator->errors()->add(
                            'task_create_trigger_webhook', 'task_create_trigger_webhook_invalid_url'
                        );
                        return false;
                    }
                }
            });
        }
    }


    public function validatedClientData(): array
    {
        $val = parent::validated();
        unset($val['clientSettings']);
        return $val;
    }


    public function validatedClientSettingsData(): array
    {
        $val = parent::validated();
        $data = $val['clientSettings'] ?? [];
        return $data;
    }


    private function isValidURL($value)
    {
        $regex = "/^(?:(?:(?:https?):)?\/\/)";
        $regex .= "(?:\S+(?::\S*)?@)?(?:(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])";
        $regex .= "(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}";
        $regex .= "(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z0-9]-*)*[a-z0-9]+)";
        $regex .= "(?:\.(?:[a-z0-9]-*)*[a-z0-9]+)*(?:\.(?:[a-z]{2,})))";
        $regex .= "(?::\d{2,5})?(?:[\/?#]\S*)?$/i";
        return preg_match($regex, $value);
    }

}
