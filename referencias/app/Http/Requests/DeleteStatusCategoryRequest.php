<?php

namespace App\Http\Requests;


class DeleteStatusCategoryRequest extends APIBaseRequest
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
                return false;
            }
            if ($statusCategory->is_irrelevant) {
                $validator->errors()->add('is_irrelevant', 'status_category_is_disabled_to_update');
                return false;
            }
            if ($statusCategory->statusCount) {
                $validator->errors()->add('delete_status_category', 'status_category_has_associated_status');
                return false;
            }
        });
    }

}
