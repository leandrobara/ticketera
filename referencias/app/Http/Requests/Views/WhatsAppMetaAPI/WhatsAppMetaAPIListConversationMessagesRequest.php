<?php

namespace App\Http\Requests\Views\WhatsAppMetaAPI;

use App\Http\Requests\APIBaseRequest;


class WhatsAppMetaAPIListConversationMessagesRequest extends APIBaseRequest
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
                $whatsAppMetaAPIConnection = request()->whatsAppMetaAPIConnection;

                if (!$client->clientSettings->enable_whatsapp_meta_api) {
                    $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_is_not_enabled');
                    return false;
                }

                if ($whatsAppMetaAPIConnection->client_id != $client->id) {
                    $validator->errors()->add(
                        'whatsapp_meta_api_connection', 'connection_does_not_belong_to_client'
                    );
                    return false;
                }

                // Permisos de visualización de conversaciones
                $permission = $client->clientSettings->whatsapp_meta_api_conversations_permission;
                if ($permission == 'none') {
                    $validator->errors()->add(
                        'whatsapp_meta_api_conversations_permission', 'conversations_view_is_restricted'
                    );
                    return false;
                }

                // owner_only / owner_leads_only: la conexión debe pertenecer al usuario logueado
                if (in_array($permission, ['owner_only', 'owner_leads_only'])) {
                    if ($whatsAppMetaAPIConnection->user_id != request()->user->id) {
                        $validator->errors()->add(
                            'whatsapp_meta_api_conversations_permission', 'connection_does_not_belong_to_user'
                        );
                        return false;
                    }
                }

            });
        }
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if (!isset($val['limit'])) {
            $val['limit'] = 200;
        }
        return $val;
    }


}
