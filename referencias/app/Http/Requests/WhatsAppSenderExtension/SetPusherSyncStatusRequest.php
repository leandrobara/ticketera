<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use App\Http\Requests\APIBaseRequest;


class SetPusherSyncStatusRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'success' => ['required', 'boolean'],
            'error' => ['present', 'string', 'nullable'],
            'pusherChannelName' => ['required', 'string'],
            'extensionUUID' => ['sometimes', 'string', 'nullable'],
            'extensionVersion' => ['sometimes', 'string', 'nullable'],
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
