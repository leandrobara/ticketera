<?php

namespace App\Http\Requests;

use DateTime;
use DateTimeZone;
use App\Rules\InLeadSaleReturnFields;


class CreateProposalInfoRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'amount' =>  ['required', 'numeric'],
            'user_id' => ['sometimes', 'integer'],
            'email_ids' => ['sometimes', 'array', 'nullable'],
            'email_ids.*' => ['sometimes', 'integer'],
            'whatsapp_sending_id' => ['sometimes', 'integer', 'nullable'],
            'whatsapp_sending_message_ids' => ['sometimes', 'array', 'nullable'],
            'whatsapp_sending_message_ids.*' => ['sometimes', 'integer'],
            'description' =>  ['sometimes', 'nullable', 'string'],
            'sent_date' =>  ['required', 'date_format:Y-m-d\TH:i:sP'],
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

        $date = (new DateTime($validated['sent_date']))->setTimezone(new DateTimeZone('UTC'));
        $validated['sent_date'] = $date->format('Y-m-d\TH:i:sP');

        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }

}
