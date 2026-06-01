<?php

namespace App\Http\Requests;


class GetWhatsAppQuickResponseRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->whatsAppQuickResponse->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'whatsapp_quick_response_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }

}
