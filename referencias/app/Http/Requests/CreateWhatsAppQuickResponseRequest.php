<?php

namespace App\Http\Requests;


class CreateWhatsAppQuickResponseRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', 'string'],
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
