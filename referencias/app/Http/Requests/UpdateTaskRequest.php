<?php

namespace App\Http\Requests;

use DateTime;
use DateTimeZone;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Validation\Rule;
use App\Rules\InTaskReturnFields;
use App\Services\API\UserService;


class UpdateTaskRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'lead_id' => ['sometimes', 'integer'],
            'user_id' => ['sometimes', 'integer'],
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string'],
            'limit_date' => ['sometimes', 'date_format:Y-m-d\TH:i:sP'],
            'status' => ['sometimes', Rule::in(['pending', 'completed'])],
            'is_important' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InTaskReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                if (request()->task->client_id != request()->input('client')->id) {
                    $validator->errors()->add('client_id', 'task_client_does_not_match_with_authenticated_client');
                }
                if (request()->input('user_id')) {
                    $user = resolve(UserService::class)->findOrFail(request()->input('user_id'));
                    if ($user->client_id != request()->input('client')->id) {
                        $validator->errors()->add('client_id', 'user_client_does_not_match_with_authenticated_client');
                    }
                }
                if (request()->input('lead_id')) {
                    $lead = Lead::findOrFail(request()->input('lead_id'));
                    if ($lead->client_id != request()->input('client')->id) {
                        $validator->errors()->add('client_id', 'lead_client_does_not_match_with_authenticated_client');
                    }
                }
            });
        }
    }


    public function validatedAttributes()
    {
        $val = parent::validated();

        if ($val['limit_date'] ?? null) {
            $date = (new DateTime($val['limit_date']))->setTimezone(new DateTimeZone('UTC'));
            $val['limit_date'] = $date->format('Y-m-d\TH:i:sP');
        }

        if ($val['fields'] ?? false) {
            unset($val['fields']);
        }
        return $val;
    }

}
