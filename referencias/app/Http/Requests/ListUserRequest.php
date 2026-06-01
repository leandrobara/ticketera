<?php
namespace App\Http\Requests;

use App\Rules\InUserReturnFields;

class ListUserRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InUserReturnFields()],
        ];
    }
}
