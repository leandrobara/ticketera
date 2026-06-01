<?php

namespace App\Http\Requests\Automations;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InAutomationNewLeadReturnFields;
use App\DTO\Automations\Parameters\ListAutomationNewLeadDTO;


class ListAutomationNewLeadRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAutomationNewLeadReturnFields()],
        ];
    }


    public function validatedDTO(): ListAutomationNewLeadDTO
    {
        $val = parent::validated();
        $dto = new ListAutomationNewLeadDTO();
        $dto->client = request()->input('client');
        return $dto;
    }

}
