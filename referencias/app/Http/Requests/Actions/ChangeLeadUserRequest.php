<?php

namespace App\Http\Requests\Actions;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InLeadReturnFields;

class ChangeLeadUserRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', 'string', new InLeadReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->lead->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'Lead client does not match with authenticated client');
            }
            if (request()->user->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'User client does not match with authenticated client');
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
