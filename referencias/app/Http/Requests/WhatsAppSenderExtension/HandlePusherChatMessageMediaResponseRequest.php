<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use App\Http\Requests\APIBaseRequest;


class HandlePusherChatMessageMediaResponseRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'userId' => ['required', 'integer'],
            'clientId' => ['required', 'integer'],
            'chatMessageId' => ['required', 'string'],
            'fromPhoneNumber' => ['required', 'integer'],
            'error' => ['present', 'nullable', 'string'],
            'chatMessageMedia' => ['required_without:error', 'array'],
        ];
    }
    

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                null; // IMPLEMENTAR (ser laxo)
            }
        });
    }

}
