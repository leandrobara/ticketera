<?php

namespace App\Http\Requests;


class CreateNewsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'type' => ['required', 'string'],
            'body' => ['required', 'string'],
            'title' => ['required', 'string'],
            'subtitle' =>  ['sometimes', 'string', 'nullable'],
            'image_url' =>  ['sometimes', 'string', 'nullable'],
            'apply_to_future_clients' => ['sometimes', 'boolean'],
            'youtube_url' =>  ['sometimes', 'string', 'nullable'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $clientyClientId = (int) config('app.clienty.client_id');
                $client = request()->input('client');
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
            });
        }
    }

}
