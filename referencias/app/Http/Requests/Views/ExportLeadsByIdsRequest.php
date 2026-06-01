<?php

namespace App\Http\Requests\Views;

use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class ExportLeadsByIdsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['required', 'array'],
            'filters.id' => ['required', 'array'],
            'filters.id.*' => ['required', 'integer'],
            'userIp' => ['sometimes', 'nullable', 'string'],
        ];
    }

}
