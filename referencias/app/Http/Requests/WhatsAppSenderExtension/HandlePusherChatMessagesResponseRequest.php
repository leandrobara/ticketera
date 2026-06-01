<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use App\Http\Requests\APIBaseRequest;


class HandlePusherChatMessagesResponseRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'userId' => ['required', 'integer'],
            'clientId' => ['required', 'integer'],
            'chatMessages' => ['present', 'array'],
            'phoneNumber' => ['required', 'string'],
            'fromPhoneNumber' => ['required', 'integer'],
            'error' => ['present', 'nullable', 'string'],
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
