<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use App\Http\Requests\APIBaseRequest;
use App\Services\API\WapBot\WapBotService;


class CreateWapBotRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'client_id' => ['required', 'integer'],
            'prompt' => ['required', 'string', 'min:500'],
            'meta_phone_number_id' => ['required', 'string'],
            'followup_1_message' => ['nullable', 'string', 'max:1000'],
            'reactivation_interval_days' => ['required', 'integer', 'min:1', 'max:999'],
            'followup_1_delay_minutes' => ['nullable', 'integer', 'min:30', 'max:1300'],
            'auto_create_lead_after_minutes' => ['nullable', 'integer', 'min:1', 'max:1400'],
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

                $follow1msg = request()->input('followup_1_message');
                $follow1delay = request()->input('followup_1_delay_minutes');

                if (($follow1msg || $follow1delay) && (!$follow1msg || !$follow1delay)) {
                    $validator->errors()->add('followup', 'followup_1_delay_required_with_message');
                    return false;
                }

                // Validar que no exista un WapBot activo con ese meta_phone_number_id
                $wapBotService = resolve(WapBotService::class);
                $existingWapBot = $wapBotService->findActiveByMetaPhoneNumberId($metaPhoneNumberId);
                if ($existingWapBot) {
                    $validator->errors()->add('meta_phone_number_id', 'wapbot_already_exists_for_this_phone_number');
                    return false;
                }

            });
        }
    }

}

