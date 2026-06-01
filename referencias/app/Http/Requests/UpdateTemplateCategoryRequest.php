<?php

namespace App\Http\Requests;


class UpdateTemplateCategoryRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'name' => ['sometimes', 'string'],
            'text_color' => ['sometimes', 'string'],
            'background_color' => ['sometimes', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $templateCategory = request()->templateCategory;
            $clientId = request()->input('client')->id;
            if ($templateCategory->client_id != $clientId) {
                $validator->errors()->add(
                    'client_id', 'template_category_client_does_not_match_with_authenticated_client'
                );
                return false;
            }
        });
    }

}
