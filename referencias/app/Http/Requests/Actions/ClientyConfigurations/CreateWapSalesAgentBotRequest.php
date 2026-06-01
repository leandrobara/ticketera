<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use App\Http\Requests\APIBaseRequest;
use App\Services\API\WapSalesAgent\WapSalesAgentBotService;


class CreateWapSalesAgentBotRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'client_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_log_enabled' => ['sometimes', 'boolean'],
            'meta_phone_number_id' => ['required', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                $clientyClientId = (int) config('app.clienty.client_id');
                $metaPhoneNumberId = request()->input('meta_phone_number_id');
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }

                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }

                // Validar que no exista un WapSalesAgentBot activo con ese meta_phone_number_id
                $service = resolve(WapSalesAgentBotService::class);
                $existing = $service->findActiveByMetaPhoneNumberId($metaPhoneNumberId);
                if ($existing) {
                    $validator->errors()->add(
                        'meta_phone_number_id', 'wap_sales_agent_bot_already_exists_for_this_phone_number'
                    );
                    return false;
                }

            });
        }
    }

}
