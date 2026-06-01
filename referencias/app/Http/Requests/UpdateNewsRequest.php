<?php

namespace App\Http\Requests;


class UpdateNewsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'subtitle' =>  ['sometimes', 'string', 'nullable'],
            'image_url' =>  ['sometimes', 'string', 'nullable'],
            'youtube_url' =>  ['sometimes', 'string', 'nullable'],
            'apply_to_future_clients' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'string', 'required_without:type,body,subtitle,image_url,youtube_url'],
            'type' => ['sometimes', 'string', 'required_without:body,title,subtitle,image_url,youtube_url'],
            'body' => ['sometimes', 'string', 'required_without:type,title,subtitle,image_url,youtube_url'],
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
