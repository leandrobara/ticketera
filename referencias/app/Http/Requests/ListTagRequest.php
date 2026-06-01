<?php

namespace App\Http\Requests;

use App\Rules\InTagReturnFields;


class ListTagRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InTagReturnFields()],
        ];
    }
}
