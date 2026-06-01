<?php

namespace App\Http\Controllers\API;

use App\Models\Task;
use App\Services\API\TaskService;
use App\Http\Resources\TaskResource;
use App\Http\Requests\GetTaskRequest;
use App\Http\Requests\DeleteTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\CreateTaskRequest;
use App\Http\Controllers\API\BaseAPIController;


class TaskController extends BaseAPIController
{

    public function getOne(Task $task, GetTaskRequest $request)
    {
        return $this->getSuccessResponse((new TaskResource($task))->loadOptionsFromRequest($request));
    }


    public function create(CreateTaskRequest $request)
    {
        $task = resolve(TaskService::class)->create($request->validatedAttributes());
        return $this->getSuccessResponse((new TaskResource($task))->loadOptionsFromRequest($request));
    }


    public function update(Task $task, UpdateTaskRequest $request)
    {
        $task = resolve(TaskService::class)->update($task, $request->validatedAttributes());
        return $this->getSuccessResponse((new TaskResource($task))->loadOptionsFromRequest($request));
    }


    public function delete(Task $task, DeleteTaskRequest $request)
    {
        $task = resolve(TaskService::class)->delete($task);
        return $this->getSuccessResponse((new TaskResource($task))->loadOptionsFromRequest($request));
    }

}
