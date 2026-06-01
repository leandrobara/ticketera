<?php

namespace App\Http\Requests\Automations;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InAutomationTaskReturnFields;
use App\DTO\Automations\Parameters\ListAutomationTaskDTO;


class ListAutomationTaskRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAutomationTaskReturnFields()],
        ];
    }


    public function validatedDTO(): ListAutomationTaskDTO
    {
        $val = parent::validated();
        $dto = new ListAutomationTaskDTO();
        $dto->client = request()->input('client');
        return $dto;
    }

}
