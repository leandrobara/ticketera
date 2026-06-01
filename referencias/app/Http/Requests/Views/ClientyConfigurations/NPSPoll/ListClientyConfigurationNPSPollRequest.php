<?php

namespace App\Http\Requests\Views\ClientyConfigurations\NPSPoll;

use App\Models\News;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;


class ListClientyConfigurationNPSPollRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'page' => ['sometimes', 'nullable', 'int'],
            'limit' => ['sometimes', 'nullable', 'int'],
            'sort' => ['sometimes', Rule::in(['date_asc', 'date_desc'])],
            'filters' => ['sometimes', 'array'],
            'filters.client_id' => ['sometimes', 'nullable', 'int'],
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
        if (!isset($val['sort'])) {
            $val['sort'] = 'date_desc';
        }
        return $val;
    }

}
