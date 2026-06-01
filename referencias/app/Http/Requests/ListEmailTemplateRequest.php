<?php

namespace App\Http\Requests;

use App\Rules\InEmailTemplateReturnFields;


class ListEmailTemplateRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['sometimes', 'array'],
            'filters.is_automation' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InEmailTemplateReturnFields()],
        ];
    }
}
