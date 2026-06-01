<?php

namespace App\Http\Requests;

use App\Rules\InTagCategoryReturnFields;


class GetTemplateCategoryRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
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
            }
        });
    }

}
