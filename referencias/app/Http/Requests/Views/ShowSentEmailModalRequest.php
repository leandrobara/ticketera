<?php

namespace App\Http\Requests\Views;

use App\Models\Email;
use App\Http\Requests\APIBaseRequest;


class ShowSentEmailModalRequest extends APIBaseRequest
{

    private $leads = null;


    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $email = request()->email;
                $client = request()->input('client');

                if ($email->client_id != $client->id) {
                    $validator->errors()->add('client_id', 'email_does_not_belong_to_client');
                    return false;
                }
            }
        });
    }

}
