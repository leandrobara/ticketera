<?php

namespace App\Http\Requests;


class OrderUpStatusCategoryRequest extends APIBaseRequest
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
            if ($statusCategory->is_irrelevant) {
                $validator->errors()->add('is_irrelevant', 'status_category_is_disabled_to_sort');
                return false;
            }
            if ($statusCategory->order == 0) {
                $validator->errors()->add('order', 'status_category_order_is_already_zero');
                return false;
            }
        });
    }

}
