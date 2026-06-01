<?php

namespace App\Http\Requests\Views;

use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WhatsAppSendingModalRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function validatedLeadIds(): Collection
    {
        $validator->after(function ($validator) {
            if (request()->whatsAppSending->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id', 'WhatsAppSending client does not match with authenticated client'
                );
            }
        });
    }

}
