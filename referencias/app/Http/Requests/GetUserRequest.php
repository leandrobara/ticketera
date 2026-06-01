<?php

namespace App\Http\Requests;

use App\Rules\InUserReturnFields;

class GetUserRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InUserReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->userToGet->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'user_client_does_not_match_with_authenticated_client');
            }
        });
    }
}
