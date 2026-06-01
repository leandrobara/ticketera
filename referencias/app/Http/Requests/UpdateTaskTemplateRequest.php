<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\API\TaskTemplateService;
use App\Rules\InTaskTemplateReturnFields;


class UpdateTaskTemplateRequest extends APIBaseRequest
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
            if (request()->taskTemplate->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'task_template',
                    'task_template_client_does_not_match_with_authenticated_client'
                );
            }

            if (!request()->input('title') && !request()->input('description')) {
                $validator->errors()->add('task_template', 'title_or_description_can_not_be_null');
            }

            $taskTemplateExists = resolve(TaskTemplateService::class)->findOneByClientAndTemplateName(
                request()->input('client'), request()->input('template_name')
            );
            if ($taskTemplateExists && $taskTemplateExists->id != request()->taskTemplate->id) {
                $validator->errors()->add('task_template', 'task_template_already_exists');
                return false;
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
