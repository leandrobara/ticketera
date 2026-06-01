<?php

namespace App\Http\Requests;

use App\Rules\InTaskTemplateReturnFields;

class GetTaskTemplateRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InTaskTemplateReturnFields()],
        ];
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
