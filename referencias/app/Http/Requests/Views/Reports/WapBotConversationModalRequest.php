<?php

namespace App\Http\Requests\Views\Reports;

use App\Http\Requests\APIBaseRequest;


class WapBotConversationModalRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->wapBotConversation->clientId != request()->input('client')->id) {
                $validator->errors()->add(
                    'clientId', 'WapBotConversation client does not match with authenticated client'
                );
            }
        });
    }

}

