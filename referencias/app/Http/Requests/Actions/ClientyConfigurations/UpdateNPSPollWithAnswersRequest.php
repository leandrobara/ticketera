<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use App\Http\Requests\APIBaseRequest;


class UpdateNPSPollWithAnswersRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'type' => ['sometimes', 'string'],
            'client_id' => ['sometimes', 'array'],
            'client_id.*' => ['required', 'integer'],
            'show_once_per_day' => ['sometimes', 'boolean'],
            'closed_date' =>  ['sometimes', 'string', 'nullable'],
            'score_title' =>  ['sometimes', 'string', 'nullable'],
            'comments_title' =>  ['sometimes', 'string', 'nullable'],
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


    public function validatedNPSPollData(): array
    {
        $val = parent::validated();
        unset($val['client_id']);
        return $val;
    }


    public function validatedNPSPollAnswersData(): array
    {
        $val = parent::validated();
        $data = ['client_id' => $val['client_id'] ?? []];
        return $data;
    }

}
