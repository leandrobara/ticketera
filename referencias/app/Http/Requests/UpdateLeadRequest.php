<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Rules\InLeadReturnFields;


class UpdateLeadRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'leads_query_id' => ['sometimes', 'integer'],
            'user_id' => ['sometimes', 'integer'],
            'landing_id' => ['sometimes', 'integer'],
            'status_id' => ['sometimes', 'integer'],
            'method' => ['sometimes', Rule::in(['form', 'chat'])],
            'quality' => ['sometimes', 'nullable', 'integer'],
            'company' => ['sometimes', 'nullable', 'string'],
            'country_code' => ['sometimes', 'string'],
            'website' => ['sometimes', 'string'],
            'other_fields' => ['sometimes', 'string'],
            'serialized_fields' => ['sometimes', 'string'],
            'message' => ['sometimes', 'nullable', 'string'],
            'utm_source' => ['sometimes', 'string'],
            'utm_medium' => ['sometimes', 'string'],
            'utm_content' => ['sometimes', 'string'],
            'utm_campaign' => ['sometimes', 'string'],
            'utm_keywords' => ['sometimes', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', 'string', new InLeadReturnFields()]
        ];
    }
    

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->lead->client->id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'lead_client_does_not_match_with_authenticated_client');
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
