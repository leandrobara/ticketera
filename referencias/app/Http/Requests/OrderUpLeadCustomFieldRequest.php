<?php

namespace App\Http\Requests;


class OrderUpLeadCustomFieldRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $client = request()->input('client');
            $leadCustomField = request()->leadCustomField;
            
            if ($leadCustomField->client_id != $client->id) {
                $validator->errors()->add(
                    'client_id', 'lead_custom_field_client_does_not_match_with_authenticated_client'
                );
            }
            if ($leadCustomField->order == 0) {
                $validator->errors()->add('order', 'status_category_order_is_already_zero');
                return false;
            }
        });
    }

}
