<?php

namespace App\Http\Requests;

use App\Rules\InTaskReturnFields;

class GetTaskRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InTaskReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->task->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'task_client_does_not_match_with_authenticated_client');
            }
        });
    }
}
