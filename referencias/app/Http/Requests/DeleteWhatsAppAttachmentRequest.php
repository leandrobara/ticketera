<?php

namespace App\Http\Requests;

use App\Models\WhatsAppAttachment;


class DeleteWhatsAppAttachmentRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                if (request()->whatsAppAttachment->client_id != request()->input('client')->id) {
                    $validator->errors()->add('client_id', 'client_does_not_match_with_authenticated_client');
                    return false;
                }
            }
        });
    }
}
