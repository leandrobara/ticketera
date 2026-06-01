<?php

namespace App\Http\Requests;

use App\Rules\InWhatsAppTemplateReturnFields;

class GetWhatsAppTemplateRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InWhatsAppTemplateReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->whatsAppTemplate->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id', 'whatsapp_template_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }
}
