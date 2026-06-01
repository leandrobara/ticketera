<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use App\Services\API\NPSPollService;
use App\Http\Requests\APIBaseRequest;


class CreateNPSPollWithAnswersRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'type' => ['present', 'string'],
            'client_id' => ['present', 'array'],
            'client_id.*' => ['required', 'integer'],
            'score_title' =>  ['required', 'string'],
            'comments_title' =>  ['required', 'string'],
            'show_once_per_day' => ['sometimes', 'boolean'],
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

                // $openedNPSPoll = resolve(NPSPollService::class)->findOneOpenedByClient($client);
                // if ($openedNPSPoll) {
                //     $validator->errors()->add('client', 'client_has_nps_poll_open');
                //     return false;
                // }
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
