<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\TaskTemplate;
use App\Services\API\TaskTemplateService;
use App\Http\Resources\TaskTemplateResource;
use App\Http\Requests\GetTaskTemplateRequest;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\DeleteTaskTemplateRequest;
use App\Http\Requests\CreateTaskTemplateRequest;
use App\Http\Requests\UpdateTaskTemplateRequest;
use App\Http\Resources\TaskTemplateResourceCollection;


class TaskTemplateController extends BaseAPIController
{

    public function list(Request $request)
    {
        $tasks = resolve(TaskTemplateService::class)->findAllByClient();
        $rs = (new TaskTemplateResourceCollection($tasks))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function getOne(TaskTemplate $taskTemplate, GetTaskTemplateRequest $request)
    {
        $resource = (new TaskTemplateResource($taskTemplate))->loadOptionsFromRequest($request);
        $resource->setVisibleFields([
            'id',
            'title',
            'description',
            'is_important',
            'template_name',
            'limit_date_days',
            'limit_date_hour',
            'automationsTask',
        ]);
        
        return $this->getSuccessResponse($resource);
    }


    public function create(CreateTaskTemplateRequest $request)
    {
        $taskTemplate = resolve(TaskTemplateService::class)->create($request->validatedAttributes());
        return $this->getSuccessResponse(
            (new TaskTemplateResource($taskTemplate))->loadOptionsFromRequest($request)
        );
    }


    public function update(TaskTemplate $taskTemplate, UpdateTaskTemplateRequest $request)
    {
        $taskTemplateUpdated = resolve(TaskTemplateService::class)->update(
            $taskTemplate, $request->validatedAttributes()
        );
        return $this->getSuccessResponse(
            (new TaskTemplateResource($taskTemplateUpdated))->loadOptionsFromRequest($request)
        );
    }


    public function delete(TaskTemplate $taskTemplate, DeleteTaskTemplateRequest $request)
    {
        $taskTemplateDeleted = resolve(TaskTemplateService::class)->delete($taskTemplate);
        return $this->getSuccessResponse(
            (new TaskTemplateResource($taskTemplateDeleted))->loadOptionsFromRequest($request)
        );
    }

}
