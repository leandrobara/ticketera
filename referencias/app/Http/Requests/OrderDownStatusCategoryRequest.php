<?php

namespace App\Http\Requests;

use App\Services\API\StatusCategoryService;


class OrderDownStatusCategoryRequest extends APIBaseRequest
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

            $statusCategories = resolve(StatusCategoryService::class)
                ->findAllByClient($client)
                ->sortBy('order')
                ->filter(fn ($sc) => !$sc->is_irrelevant)
                ->last();
            ;
            $lastOrderPosition = $statusCategories->order;
            if ($statusCategory->order == $lastOrderPosition) {
                $validator->errors()->add('order', 'status_category_has_already_last_order_position');
                return false;
            }
        });
    }

}
