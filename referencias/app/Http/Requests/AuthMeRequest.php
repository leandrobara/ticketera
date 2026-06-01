<?php

namespace App\Http\Requests;

class AuthMeRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->user->client_id != request()->input('client')->id) {
                $validator->errors()->add('user_id', 'user_does_not_match_with_authenticated_client');
            }
        });
    }
}
