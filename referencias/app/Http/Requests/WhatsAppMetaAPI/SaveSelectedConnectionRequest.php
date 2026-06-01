<?php

namespace App\Http\Requests\WhatsAppMetaAPI;

use App\Http\Requests\APIBaseRequest;


class SaveSelectedConnectionRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'meta_waba_id' => ['required', 'string'],
            'meta_phone_number_id' => ['required', 'string'],
        ];
    }

}
