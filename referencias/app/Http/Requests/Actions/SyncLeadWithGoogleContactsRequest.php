<?php

namespace App\Http\Requests\Actions;

use App\Rules\InLeadReturnFields;
use App\Http\Requests\APIBaseRequest;


class SyncLeadWithGoogleContactsRequest extends APIBaseRequest
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
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                if (request()->lead->client_id != request()->input('client')->id) {
                    $validator->errors()->add('client_id', 'Lead client does not match with authenticated client');
                }
            });
        }
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
