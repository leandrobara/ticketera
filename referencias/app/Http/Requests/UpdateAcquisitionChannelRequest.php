<?php

namespace App\Http\Requests;

use App\Repositories\StatusRepository;
use App\Rules\InAcquisitionChannelReturnFields;
use App\Repositories\AcquisitionChannelRepository;


class UpdateAcquisitionChannelRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'name' => ['sometimes', 'string'],
            'text_color' => ['sometimes', 'string'],
            'background_color' => ['sometimes', 'string'],
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
                return false;
            }

            if (request()->input('name')) {
                $existentChannel = resolve(AcquisitionChannelRepository::class)->findOneByClientAndName(
                    request()->input('client'), request()->input('name')
                );
                if ($existentChannel && $existentChannel->id != request()->channel->id) {
                    $validator->errors()->add('name', 'acquisition_channel_already_exists');
                    return false;
                }
            }
        });
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
