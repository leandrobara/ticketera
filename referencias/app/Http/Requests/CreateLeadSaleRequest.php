<?php

namespace App\Http\Requests;

use DateTime;
use DateTimeZone;
use App\Rules\InLeadSaleReturnFields;


class CreateLeadSaleRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'amount' =>  ['required', 'numeric'],
            'user_id' => ['sometimes', 'integer'],
            'is_manually_created' =>  ['sometimes', 'boolean'],
            'description' =>  ['sometimes', 'nullable', 'string'],
            'sale_date' =>  ['sometimes', 'date_format:Y-m-d\TH:i:sP'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', 'string', new InLeadSaleReturnFields()]
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
        $validated = parent::validated();

        if ($validated['sale_date'] ?? null) {
            $date = (new DateTime($validated['sale_date']))->setTimezone(new DateTimeZone('UTC'));
            $validated['sale_date'] = $date->format('Y-m-d\TH:i:sP');
        }

        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }

}
