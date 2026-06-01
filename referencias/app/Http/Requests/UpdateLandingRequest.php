<?php

namespace App\Http\Requests;

use App\Rules\InLandingReturnFields;

class UpdateLandingRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'leads_landing_id' => ['sometimes', 'integer'],
            'url' => ['sometimes', 'string'],
            'enabled' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InLandingReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->landing->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'landing_client_does_not_match_with_authenticated_client');
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
