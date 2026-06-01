<?php

namespace App\Http\Requests\Integration;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;


class CreateLeadSaleRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'amount' =>  ['required', 'numeric'],
            'description' =>  ['sometimes', 'nullable', 'string'],
            'sale_date' =>  ['sometimes', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!request()->input('client')->clientSettings->enable_integration_api) {
                $validator->errors()->add('client', 'integration_api_is_not_enabled_for_this_client');
                return false;
            }
            if (request()->lead->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'client_does_not_match_with_authenticated_client');
                return false;
            }
        });
    }


    public function validatedAttributes()
    {
        $validated = parent::validated();

        $date = new DateTime();
        if ($validated['sale_date'] ?? null) {
            $date = (new DateTime($validated['sale_date']))->setTimezone(new DateTimeZone('UTC'));
        }

        $validated['sale_date'] = $date->format('Y-m-d\TH:i:sP');

        return $validated;
    }

}
