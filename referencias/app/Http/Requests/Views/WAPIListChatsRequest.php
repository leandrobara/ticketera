<?php

namespace App\Http\Requests\Views;

use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WAPIListChatsRequest extends APIBaseRequest
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
                $loginUser = request()->input('user');
                
                if (!request()->client->clientSettings->enable_wapi) {
                    $validator->errors()->add('wapi', 'wapi_is_not_enabled');
                    return false;
                }

                if ($loginUser->wapi_is_paused) {
                    $validator->errors()->add('wapi', 'wapi_is_paused');
                    return false;
                }
            });
        }
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        $val['limit'] = $val['limit'] ?? 200;
        return $val;
    }

}
