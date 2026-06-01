<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class UpdateLeadContactEmailValidAndSubscribedStatusRequest extends APIBaseRequest
{

    private array $leadContactEmails = [];


    public function rules()
    {
        return [
            // Siempre recibe un array de emails (máximo 25)
            'emails' => ['required', 'array', 'max:25'],
            'emails.*.email' => ['required', 'email'],
            'emails.*.isValid' => ['required', 'boolean'],
            'emails.*.isSubscribed' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $clientyClientId = (int) config('app.clienty.client_id');
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
            }
        });
    }


    public function getEmails()
    {
        return collect($this->input('emails'))->map(function ($email) {
            return [
                'email' => $email['email'],
                'isValid' => $email['isValid'],
                'isSubscribed' => $email['isSubscribed'],
            ];
        });
    }
    
}
