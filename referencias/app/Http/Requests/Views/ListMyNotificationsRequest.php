<?php

namespace App\Http\Requests\Views;

use App\Rules\IsTaskStatusOrArray;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class ListMyNotificationsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'task.limit' => ['sometimes', 'int'],
            'gmail.limit' => ['sometimes', 'int'],
            'task.sort' => ['sometimes', 'string'],
            'task.filters' => ['sometimes', 'array'],
            'task.filters.is_important' => ['sometimes', 'boolean'],
            'task.filters.status' => ['sometimes', new IsTaskStatusOrArray()],
            'task.filters.user_id' => ['sometimes', new IsRequiredIntegerOrArray()],
        ];
    }


    public function getValidatedTasksParams()
    {
        $validated = parent::validated();
        if (!($validated['task']['limit'] ?? null)) {
            $validated['task']['limit'] = 99;
        }
        return $validated['task'];
    }


    public function getValidatedGmailParams()
    {
        $validated = parent::validated();
        if (!($validated['gmail']['limit'] ?? null)) {
            $validated['gmail']['limit'] = 99;
        }
        return $validated['gmail'];
    }

}
