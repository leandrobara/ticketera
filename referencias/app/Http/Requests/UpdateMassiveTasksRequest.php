<?php

namespace App\Http\Requests;

use DateTime;
use DateTimeZone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\Rule;
use App\Rules\InTaskReturnFields;
use Illuminate\Support\Collection;


class UpdateMassiveTasksRequest extends APIBaseRequest
{

    protected $tasks = [];

    public function rules()
    {
        return [
            'task_id' => ['required', 'array'],
            'description' => ['sometimes', 'string'],
            'title' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in(['pending', 'completed'])],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $title = request()->input('title');
                $status = request()->input('status');
                $taskIds = request()->input('task_id');
                $clientId = request()->input('client')->id;
                $description = request()->input('description');
                
                $tasksCount = Task::where('client_id', $clientId)->whereIn('id', $taskIds)->count();
                $taskIdsCount = count($taskIds);
                if ($taskIdsCount != $tasksCount) {
                    $validator->errors()->add('task_id', 'Some tasks do not exists');
                    return false;
                }

                if (!$title && !$status && !$description) {
                    $validator->errors()->add('tasks_massive', 'Update fields are empty');
                    return false;
                }
            });
        }
    }


    public function getTaskIds(): Collection
    {
        return collect(request()->input('task_id'));
    }


    public function getAttributes(): array
    {
        $validated = parent::validated();
        unset($validated['task_id']);
        return $validated;
    }
}
