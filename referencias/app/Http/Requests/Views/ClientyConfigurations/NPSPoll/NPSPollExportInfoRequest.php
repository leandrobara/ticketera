<?php

namespace App\Http\Requests\Views\ClientyConfigurations\NPSPoll;

use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class NPSPollExportInfoRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $NPSPoll = request()->NPSPoll;
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
                if ($NPSPoll->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id', 'nps_poll_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }
            });
        }
    }

}
