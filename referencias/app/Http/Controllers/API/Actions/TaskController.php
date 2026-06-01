<?php

namespace App\Http\Controllers\API\Actions;

use App\Models\Task;
use App\Models\User;
use App\Services\API\TaskService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\UpdateMassiveTasksRequest;
use App\Http\Requests\DeleteMassiveTasksRequest;
use App\Http\Requests\SetMassiveTasksUserRequest;


class TaskController extends BaseAPIController
{

    public function updateMassiveTasks(UpdateMassiveTasksRequest $req)
    {
        $changedTaskIds = resolve(TaskService::class)->updateMassiveTasks($req->getTaskIds(), $req->getAttributes());
        return $this->getSuccessResponse(['task_id' => $changedTaskIds]);
    }


    public function deleteMassiveTasks(DeleteMassiveTasksRequest $req)
    {
        $deletedTaskIds = resolve(TaskService::class)->deleteMassiveTasks($req->getTaskIds());
        return $this->getSuccessResponse(['task_id' => $deletedTaskIds]);
    }


    public function setMassiveTasksUser(User $newUser, SetMassiveTasksUserRequest $req)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        resolve(TaskService::class)->setMassiveTasksUser($req->getTaskIds(), $newUser);
        return $this->getSuccessResponse(['task_id' => $req->getTaskIds()]);
    }

}
