<?php

namespace App\Http\Requests\Actions;

use App\Http\Requests\APIBaseRequest;


class ChangeLeadsAcquisitionChannelRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $correctClient = request()->newChannel->client_id === request()->input('client')->id;
                if (!$correctClient) {
                    $validator->errors()->add(
                        'client_id',
                        'new_acquisition_channel_client_does_not_match_with_authenticated_client'
                    );
                }

                $correctClient = request()->originalChannel->client_id === request()->input('client')->id;
                if (!$correctClient) {
                    $validator->errors()->add(
                        'client_id',
                        'original_acquisition_channel_client_does_not_match_with_authenticated_client'
                    );
                }
            });
        }
    }

    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }
}
