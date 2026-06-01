<?php

namespace App\Http\Requests;

use DateTime;
use DateTimeZone;
use App\Rules\InLeadSaleReturnFields;


class UpdateLeadSaleRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'amount' =>  ['sometimes', 'numeric'],
            'user_id' => ['sometimes', 'integer'],
            'description' =>  ['sometimes', 'nullable', 'string'],
            'sale_date' =>  ['sometimes', 'date_format:Y-m-d\TH:i:sP'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', "string", new InLeadSaleReturnFields() ]
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->lead->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'client_does_not_match_with_authenticated_client');
            }
        });
    }


    public function validatedAttributes()
    {
        $val = parent::validated();

        if ($val['sale_date'] ?? null) {
            $date = (new DateTime($val['sale_date']))->setTimezone(new DateTimeZone('UTC'));
            $val['sale_date'] = $date->format('Y-m-d\TH:i:sP');
        }

        if ($val['fields'] ?? false) {
            unset($val['fields']);
        }
        return $val;
    }

}
