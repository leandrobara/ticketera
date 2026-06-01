<?php

namespace App\Http\Requests;

use App\Services\API\TaskTemplateService;
use App\Rules\InTaskTemplateReturnFields;

class CreateTaskTemplateRequest extends APIBaseRequest
{
    public function rules()
    {
        $limitDateHourRule = [
            'sometimes',
            'nullable',
            'string',
            'size:5',
            'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'
        ];

        return [
            'is_important' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
            'template_name' => ['required', 'string', 'max:120'],
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'limit_date_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'limit_date_hour' => $limitDateHourRule,
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InTaskTemplateReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                if (!request()->input('title') && !request()->input('description')) {
                    $validator->errors()->add('task_template', 'title_or_description_can_not_be_null');
                }

                $existentsTaskTemplate = resolve(TaskTemplateService::class)->findOneByClientAndTemplateName(
                    request()->input('client'), request()->input('template_name')
                );
                if ($existentsTaskTemplate) {
                    $validator->errors()->add('task_template', 'task_template_already_exists');
                    return false;
                }
            }
        });
    }


    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }
}
