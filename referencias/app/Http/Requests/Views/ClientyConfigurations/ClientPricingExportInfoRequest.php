<?php

namespace App\Http\Requests\Views\ClientyConfigurations;

use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;


class ClientPricingExportInfoRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'order' => ['sometimes', 'in:asc,desc'],
            'filters' => ['sometimes', 'array'],
            'filters.name' => ['sometimes', 'nullable', 'string'],
            'filters.customWapFilter' => [
                'string',
                'nullable',
                'sometimes',
                'in:enabled_wapi,enabled_wap_sender,disabled_wapi_and_wap_sender',
            ],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $loginUser = request()->user;
                $client = request()->input('client');
                $isClientyAdminUser = $loginUser->is_clienty_admin_user;
                $clientyClientId = (int) config('app.clienty.client_id');

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


    public function validated($key = null, $default = null): array
    {
        $val = parent::validated();
        $val['sort'] = $val['sort'] ?? 'asc';
        $val['with'] = ['clientSettings'];
        return $val;
    }

}
