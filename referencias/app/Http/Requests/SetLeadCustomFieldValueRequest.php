<?php

namespace App\Http\Requests;


class SetLeadCustomFieldValueRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'value' => 'string|nullable',
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->lead->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'lead_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }


    public function getValueStr()
    {
        $validated = parent::validated();
        $value = $validated['value'] ?? false;
        if (trim($value) !== '0' && !$value) {
            $validated['value'] = null;
        }
        return trim($validated['value']);
    }
}
