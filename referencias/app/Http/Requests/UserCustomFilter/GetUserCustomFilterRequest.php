<?php

namespace App\Http\Requests\UserCustomFilter;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InUserCustomFilterReturnFields;

class GetUserCustomFilterRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InUserCustomFilterReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $user = request()->input('user');
                if (request()->userCustomFilter->client_id != $client->id) {
                    $validator->errors()->add('client_id', 'filter_client_does_not_match_with_authenticated_client');
                    return false;
                }

                if (request()->userCustomFilter->user_id != $user->id) {
                    $validator->errors()->add('user_id', 'filter_user_does_not_match_with_authenticated_client');
                    return false;
                }
            }
        });
    }
}
