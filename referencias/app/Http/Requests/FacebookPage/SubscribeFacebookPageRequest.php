<?php

namespace App\Http\Requests\FacebookPage;

use App\Http\Requests\APIBaseRequest;
use Illuminate\Support\Facades\Log;

class SubscribeFacebookPageRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        // check the referer is facebook
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $referer = request()->header('referer');
                // if (!preg_match("/facebook/", $referer)) {
                //     Log::alert('request not refered from facebook', ['referer' => $referer]);
                // }
            }
        });
    }
}
