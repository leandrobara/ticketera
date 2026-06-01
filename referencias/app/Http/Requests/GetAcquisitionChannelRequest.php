<?php

namespace App\Http\Requests;

use App\Rules\InAcquisitionChannelReturnFields;

class GetAcquisitionChannelRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAcquisitionChannelReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->channel->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'acquisition_channel_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }
}
