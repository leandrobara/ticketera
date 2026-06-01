<?php

namespace App\Http\Requests;


class UpdateWhatsAppQuickResponseRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'title' => ['sometimes', 'string'],
            'body' => ['sometimes', 'string'],
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


    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }

}
