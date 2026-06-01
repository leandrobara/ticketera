<?php

namespace App\Http\Requests;

class DeleteTaskTemplateRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->taskTemplate->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'task_template',
                    'task_template_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }
}
