<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Session;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AuthResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $user = $this->resource['user'];
        $response = [
            'user' => [
                'id' => $user->id,
                'type' => $user->type,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'username' => $user->username,
                'last_name' => $user->last_name,
                'isSuperUser' => $this->resource['isSuperUser'],
                'is_clienty_admin_user' => $user->is_clienty_admin_user,
                'wap_sender_session_phone_number' => $user->wap_sender_session_phone_number,
                'has_whatsapp_meta_api_connection' => $user->whatsAppMetaAPIConnection ? true : false,
            ],
            'superUser' => null,
            'client' => [
                'id' => $this->resource['client']->id,
                'timezone' => $this->resource['client']->timezone,
                'country_code' => $this->resource['client']->country_code,
                'wapBotsCount' => $this->resource['client']->wapBots->count(),
            ],
            'clientSettings' => $this->resource['clientSettings'],
            'apiToken' => [
                'token' => $this->resource['token'],
                'expiresAt' => $user->api_token_expiration_date->format(config('app.datetime_format'))
            ],
            'landedUrl' => [
                Session::get('landedUri', '/'),
            ],
        ];

        if ($this->resource['superUser']) {
            $response['superUser'] = [
                'id' => $this->resource['superUser']->id,
                'name' => $this->resource['superUser']->name,
                'email' => $this->resource['superUser']->email,
                'last_name' => $this->resource['superUser']->last_name,
            ];
        }
        return $response;
    }
}
