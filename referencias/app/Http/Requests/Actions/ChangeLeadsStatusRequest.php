<?php

namespace App\Http\Requests\Actions;

use App\Http\Requests\APIBaseRequest;


class ChangeLeadsStatusRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $correctClient = request()->newStatus->client_id === request()->input('client')->id;
                if (!$correctClient) {
                    $validator->errors()->add(
                        'client_id',
                        'New status client does not match with authenticated client'
                    );
                }

                $correctClient = request()->originalStatus->client_id === request()->input('client')->id;
                if (!$correctClient) {
                    $validator->errors()->add(
                        'client_id',
                        'Original status client does not match with authenticated client'
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
