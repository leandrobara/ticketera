<?php

namespace App\Http\Requests;


class CountStatusCategoryStatusRelatedRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $client = request()->input('client');
            $statusCategory = request()->statusCategory;
            if ($statusCategory->client_id != $client->id) {
                $validator->errors()->add(
                    'client_id', 'status_category_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }
}
