<?php

namespace App\Http\Requests\Actions;
use App\Http\Requests\APIBaseRequest;


class UpdateNewsWithNotificationsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'type' => ['sometimes', 'string'],
            'body' => ['sometimes', 'string'],
            'title' => ['sometimes', 'string'],
            'client_id' => ['present', 'array'],
            'client_id.*' => ['required', 'integer'],
            'force_modal_show' => ['sometimes', 'boolean'],
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
                $loginUser = request()->user;
                $client = request()->input('client');
                $isClientyAdminUser = $loginUser->is_clienty_admin_user;
                $clientyClientId = (int) config('app.clienty.client_id');

                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;
                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                if (!$isSuperUser && !$isClientyAdminUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
            });
        }
    }


    public function validatedNewsData(): array
    {
        $val = parent::validated();
        unset($val['client_id']);
        return $val;
    }


    public function validatedNewsNotificationsData(): array
    {
        $val = parent::validated();
        $data = ['client_id' => $val['client_id'] ?? []];
        return $data;
    }

}
