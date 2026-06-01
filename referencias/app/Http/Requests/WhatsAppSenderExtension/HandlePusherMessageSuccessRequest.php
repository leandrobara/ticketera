<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use App\Http\Requests\APIBaseRequest;


class HandlePusherMessageSuccessRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'userId' => ['required', 'integer'],
            'clientId' => ['required', 'integer'],
            'browserTrackingKey' => ['required', 'string'],
            'whatsAppSendingMessageId' => ['required', 'integer'],
            'extensionUUID' => ['sometimes', 'string', 'nullable'],
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
