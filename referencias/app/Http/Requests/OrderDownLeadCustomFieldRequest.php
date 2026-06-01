<?php

namespace App\Http\Requests;

use App\Services\API\LeadCustomFieldService;


class OrderDownLeadCustomFieldRequest extends APIBaseRequest
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

            $lastLeadCustomField = resolve(LeadCustomFieldService::class)
                ->findAllByClient($client)
                ->sortBy('order')
                ->last();
            ;
            $lastOrderPosition = $lastLeadCustomField->order;
            if ($leadCustomField->order == $lastOrderPosition) {
                $validator->errors()->add('order', 'lead_custom_field_has_already_last_order_position');
                return false;
            }
        });
    }

}
