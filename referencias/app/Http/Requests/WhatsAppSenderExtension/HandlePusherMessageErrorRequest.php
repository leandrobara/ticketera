<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use App\Http\Requests\APIBaseRequest;


class HandlePusherMessageErrorRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'errorCode' => ['required', 'string'],
            'browserTrackingKey' => ['required', 'string'],
            'extensionUUID' => ['sometimes', 'string', 'nullable'],
        ];
    }
    

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                null; // Nada por ahora.
            }
        });
    }

}
