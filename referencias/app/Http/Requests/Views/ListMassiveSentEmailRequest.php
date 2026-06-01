<?php

namespace App\Http\Requests\Views;

use App\Http\Requests\APIBaseRequest;


class ListMassiveSentEmailRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'sort' => ['sometimes', 'string'],
            'page' => ['sometimes', 'nullable', 'int'],
            'limit' => ['sometimes', 'nullable', 'int'],
            'filters.user_id' => ['sometimes', 'nullable', 'array'],
            'filters.send_date_end' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'filters.send_date_start' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        $user = request()->input('user');
        $isSalesUser = ($user['type'] == 'sales');
        if ($isSalesUser) {
            $validated['filters']['user_id'] = [$user->id];
        }
        return $validated;
    }

}
