<?php

namespace App\Http\Requests\FacebookPage;

use App\Http\Requests\APIBaseRequest;

class UnsubscribeFacebookPageRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        // check the refere is facebook
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                if (request()->clientFacebookPage->client_id != request()->client->id) {
                    $validator->errors()->add(
                        'client',
                        'facebook_page_client_does_not_match_with_authenticated_client'
                    );
                }
            }
        });
    }
}
