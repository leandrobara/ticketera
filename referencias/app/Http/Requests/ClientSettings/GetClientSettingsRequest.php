<?php

namespace App\Http\Requests\ClientSettings;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InClientSettingsReturnFields;

class GetClientSettingsRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InClientSettingsReturnFields()],
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
