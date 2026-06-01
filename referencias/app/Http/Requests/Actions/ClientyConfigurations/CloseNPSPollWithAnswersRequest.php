<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use DateTime;
use DateTimeZone;
use App\Http\Requests\APIBaseRequest;


class CloseNPSPollWithAnswersRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'close_reason' =>  ['required', 'string','in:admin_close'],
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


    public function dataToClose(): array
    {
        $validated = parent::validated();

        $timeZone = new DateTimeZone(request()->input('client')->timezone);
        $currentDate = (new DateTime())->setTimezone($timeZone)->format('Y-m-d\TH:i:sP');

        return [
            'nps_poll_data' => [
                'closed_date' => $currentDate,
            ],
            'nps_poll_answer_data' => [
                'closed_date' => $currentDate,
                'close_reason' => $validated['close_reason'],
            ],
        ];
    }

}
