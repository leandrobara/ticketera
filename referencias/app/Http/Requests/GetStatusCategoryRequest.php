<?php

namespace App\Http\Requests;

use App\Rules\InTagCategoryReturnFields;


class GetStatusCategoryRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $statusCategory = request()->statusCategory;
            $clientId = request()->input('client')->id;
            if ($statusCategory->client_id != $clientId) {
                $validator->errors()->add(
                    'client_id', 'status_category_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }

}
