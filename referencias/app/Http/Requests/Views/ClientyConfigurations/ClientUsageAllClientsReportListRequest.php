<?php

namespace App\Http\Requests\Views\ClientyConfigurations;

use DateTime;
use DateTimeZone;
use App\Models\Client;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class ClientUsageAllClientsReportListRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['required', 'array'],
            'filters.date_end' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'filters.date_start' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
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
            });
        }
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        $dateEnd = (new DateTime($val['filters']['date_end']))->setTimezone(new DateTimeZone('UTC'));
        $val['filters']['date_end'] = $dateEnd;
        $dateStart = (new DateTime($val['filters']['date_start']))->setTimezone(new DateTimeZone('UTC'));
        $val['filters']['date_start'] = $dateStart;
        return $val;
    }

}
