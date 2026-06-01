<?php

namespace App\Http\Requests\UserCustomFilter;

use App\Http\Requests\APIBaseRequest;

class DeleteUserCustomFilterRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                if (request()->userCustomFilter->client_id != $this->client->id) {
                    $validator->errors()->add('client_id', 'filter_client_does_not_match_with_authenticated_client');
                    return false;
                }

                if (request()->userCustomFilter->user_id != $this->user->id) {
                    $validator->errors()->add('user_id', 'filter_user_does_not_match_with_authenticated_client');
                    return false;
                }
            }
        });
    }
}
