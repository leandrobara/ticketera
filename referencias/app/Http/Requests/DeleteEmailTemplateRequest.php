<?php

namespace App\Http\Requests;

class DeleteEmailTemplateRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->emailTemplate->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'email_template_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }
}
