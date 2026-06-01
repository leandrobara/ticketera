<?php

namespace App\Http\Requests\Views;

use App\Models\Email;
use App\Http\Requests\APIBaseRequest;


class ShowSentMassiveEmailModalRequest extends APIBaseRequest
{

    private $email = null;


    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $externalMassiveId = request()->externalMassiveId;

                $email = Email::where('external_massive_id', $externalMassiveId)->first();

                if (!$email) {
                    $validator->errors()->add('external_massive_id', 'email_does_not_exist');
                    return false;
                }
                if ($email->client_id != $client->id) {
                    $validator->errors()->add('client_id', 'email_does_not_belong_to_client');
                    return false;
                }
                
                $this->email = $email;
            }
        });
    }

    public function getEmail()
    {
        return $this->email;
    }

}
