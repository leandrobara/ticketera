<?php

namespace App\Http\Resources\Views\ClientModal;

use App\Models\Client;
use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class ClientSettingModalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [
            'id' => $this->id,
            'name' => $this->name,
            'timezone' => $this->timezone,
        ];

        $response = $this->loadSettings($response);
        $response = $this->loadTimezoneByCountryCode($response);
        return $response;
    }


    private function loadSettings(array $response)
    {
        if (!$this->resource->relationLoaded('clientSettings')) {
            $this->resource->load('clientSettings');
        }

        $visibleFields = [
            'id',
            'enable_wapi',
            'bonus_users',
            'acquired_users',
            'acquired_landings',
            'enable_integration_api',
            'show_non_payment_alert',
            'force_whatsapp_meta_api',
            'enable_whatsapp_meta_api',
            'daily_email_sending_quota',
            'lead_sale_trigger_webhook',
            'lead_create_trigger_webhook',
            'monthly_email_sending_quota',
            'task_create_trigger_webhook',
            'enable_wapi_conversation_chat',
            'lead_sale_trigger_make_webhook',
            'enable_leads_sales_bulk_upload',
            'acquired_extra_emails_sendings',
            'lead_sale_trigger_zapier_webhook',
            'lead_create_trigger_make_webhook',
            'enable_whatsapp_sender_extension',
            'task_create_trigger_make_webhook',
            'enable_whatsapp_sender_job_sending',
            'enable_whatsapp_auto_phone_formatting',
            'task_create_trigger_zapier_webhook',
            'lead_create_trigger_zapier_webhook',
            'lead_status_change_trigger_webhook',
            'lead_status_change_trigger_make_webhook',
            'lead_status_change_trigger_zapier_webhook',
            'whatsapp_meta_api_conversations_permission',
            'whatsapp_sender_daily_sending_quota_per_user',
        ];
        $clientSettingsRs = new ClientResource($this->resource->clientSettings);
        $clientSettingsRs->setVisibleFields($visibleFields);
        $response['clientSettings'] = $clientSettingsRs;
        return $response;
    }


    private function loadTimezoneByCountryCode(array $response)
    {
        $timezoneList = config('timezone.code');
        $countryCode = $this->resource->country_code;

        $response['timezoneList'] = array_key_exists($countryCode, $timezoneList) ?
            $timezoneList[$countryCode] :
            [$this->resource->timezone]
        ;
        return $response;
    }

}
