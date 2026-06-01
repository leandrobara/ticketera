<?php

namespace App\Http\Requests\Views\ClientyConfigurations;

use DateTime;
use DateTimeZone;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;
use App\Rules\IsRequiredNullableIntegerOrArray;

class ClientNotificationListModalRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'limit' => ['sometimes', 'int'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                $clientyClientId = (int) config('app.clienty.client_id');
                $isClientyAdminUser = request()->user->is_clienty_admin_user;
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                
                if (!$isSuperUser && !$isClientyAdminUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
            });
        }
    }


    public function getLimit($key = null, $default = null)
    {
        $val = parent::validated();
        return $val['limit'] ?? 100;
    }

}
