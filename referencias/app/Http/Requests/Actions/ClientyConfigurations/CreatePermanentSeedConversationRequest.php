<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use App\Http\Requests\APIBaseRequest;


class CreatePermanentSeedConversationRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'customer_phone_number' => ['required', 'string', 'min:8', 'max:20'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                $clientyClientId = (int) config('app.clienty.client_id');
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }

                // Validar que el teléfono sea numérico
                $phoneNumber = $this->input('customer_phone_number');
                if (!is_numeric($phoneNumber)) {
                    $validator->errors()->add('customer_phone_number', 'phone_must_be_numeric');
                    return false;
                }
            });
        }
    }


    public function getCustomerPhoneNumber(): string
    {
        return (string) $this->input('customer_phone_number');
    }

}
