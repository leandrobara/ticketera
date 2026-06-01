<?php

namespace App\Http\Resources\Views\TaskNotification;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class TaskNotificationResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toArray($request)
    {
        $response = ['expired' => [], 'expiresToday' => []];
        foreach ($this->collection as $task) {
            $taskRs = new TaskNotificationItemResource($task);
            $index = ($task->expiresToday) ? 'expiresToday' : 'expired';
            $response[$index][] = new TaskNotificationItemResource($task);
        }
        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }

}
