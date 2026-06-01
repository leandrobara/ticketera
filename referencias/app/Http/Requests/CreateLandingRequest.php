<?php

namespace App\Http\Requests;

use App\Rules\InLandingReturnFields;

class CreateLandingRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'leads_landing_id' => ['sometimes', 'integer'],
            'url' => [
                'required',
                'regex:/[(http(s)?):\/\/(www\.)?a-zA-Z0-9@:%._\+~#=]' .
                '{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&\/\/=]*)/'
            ],
            'enabled' => ['required', 'boolean'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InLandingReturnFields()],
        ];
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
