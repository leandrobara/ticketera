<?php

namespace App\Http\Requests;

use App\Rules\InTagCategoryReturnFields;


class UpdateTagCategoryRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'name' => ['sometimes', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InTagCategoryReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->tagCategory->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'tag_category_client_does_not_match_with_authenticated_client');
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
