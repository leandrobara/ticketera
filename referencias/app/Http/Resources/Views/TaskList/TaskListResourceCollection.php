<?php

namespace App\Http\Resources\Views\TaskList;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class TaskListResourceCollection extends ResourceCollection
{

    use HandlePagination;

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $task) {
            $response[] = new TaskListItemResource($task);
        }
        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }

}
