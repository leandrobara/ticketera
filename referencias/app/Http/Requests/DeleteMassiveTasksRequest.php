<?php

namespace App\Http\Requests;

use DateTime;
use DateTimeZone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\Rule;
use App\Rules\InTaskReturnFields;
use Illuminate\Support\Collection;


class DeleteMassiveTasksRequest extends APIBaseRequest
{
    protected $tasks = [];

    public function rules()
    {
        return [
            'task_id' => ['required', 'array'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $taskIds = request()->input('task_id');
                $clientId = request()->input('client')->id;
                
                $tasksCount = Task::where('client_id', $clientId)->whereIn('id', $taskIds)->count();
                $taskIdsCount = count($taskIds);
                if ($taskIdsCount != $tasksCount) {
                    $validator->errors()->add('task_id', 'Some tasks do not exists');
                    return false;
                }
            });
        }
    }


    public function getTaskIds(): Collection
    {
        return collect(request()->input('task_id'));
    }

}
