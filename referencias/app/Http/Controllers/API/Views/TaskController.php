<?php
namespace App\Http\Controllers\API\Views;

use App\Exports\TaskExport;
use App\Services\API\Views\TaskService;
use App\Http\Requests\Views\ListTaskRequest;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\NotificationsTasksRequest;
use App\Http\Resources\Views\TaskList\TaskListResourceCollection;


class TaskController extends BaseAPIController
{

    public function list(ListTaskRequest $req)
    {
        $tasks = resolve(TaskService::class)->findPaginatedByFiltersAndClient($req->validated(), $req->client);
        return $this->getSuccessResponse(new TaskListResourceCollection($tasks));
    }


    public function listIds(ListTaskRequest $req)
    {
        ini_set('memory_limit', '2000M');
        $ids = resolve(TaskService::class)->listIds($req->validated());
        return $this->getSuccessResponse($ids);
    }


    public function pendingCount()
    {
        $pendingTasksCount = resolve(TaskService::class)->countPending();
        return $this->getSuccessResponse($pendingTasksCount);
    }


    // Deprecado: borrar
    public function notifications(/*NotificationsTasksRequest $req*/)
    {
        // $tasks = resolve(TaskService::class)->findTasksToNotify($req->client, $req->validated());
        // return $this->getSuccessResponse(new TaskNotificationResourceCollection($tasks));
        return $this->getSuccessResponse(['expired' => [], 'expiresToday' => []]);
    }


    public function export(ListTaskRequest $request)
    {
        $response = resolve(TaskService::class)->findPaginatedByFiltersAndClient($request->validated());
        return (new TaskExport($response))->download('clienty-reporte-de-tareas.xlsx');
    }

}