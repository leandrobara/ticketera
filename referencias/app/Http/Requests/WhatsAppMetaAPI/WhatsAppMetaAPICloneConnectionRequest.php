<?php

namespace App\Http\Requests\WhatsAppMetaAPI;

use App\Http\Requests\APIBaseRequest;


class WhatsAppMetaAPICloneConnectionRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                if (!request()->client->clientSettings->enable_whatsapp_meta_api) {
                    $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_is_not_enabled');
                    return false;
                }

                // Validar que la conexión a clonar pertenezca al mismo cliente
                $sourceConnection = request()->sourceConnection;
                if ($sourceConnection->client_id != request()->client->id) {
                    $validator->errors()->add('connection', 'connection_does_not_belong_to_client');
                    return false;
                }
            });
        }
    }

}

