<?php

namespace App\Http\Requests;

use App\Rules\InAcquisitionChannelReturnFields;
use App\Repositories\AcquisitionChannelRepository;


class CreateAcquisitionChannelRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAcquisitionChannelReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $existentChannel = resolve(AcquisitionChannelRepository::class)->findOneByClientAndName(
                    request()->input('client'), request()->input('name')
                );
                if ($existentChannel) {
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
