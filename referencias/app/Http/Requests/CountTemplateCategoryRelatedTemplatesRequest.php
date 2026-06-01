<?php

namespace App\Http\Requests;


class CountTemplateCategoryRelatedTemplatesRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $client = request()->input('client');
            $templateCategory = request()->templateCategory;
            if ($templateCategory->client_id != $client->id) {
                $validator->errors()->add(
                    'client_id', 'template_category_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }

}
