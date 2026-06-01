<?php

namespace App\Http\Controllers\API\Automations;

use App\Models\AutomationTask;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Automations\AutomationTaskService;
use App\Http\Resources\Automations\AutomationTaskResource;
use App\Http\Requests\Automations\ListAutomationTaskRequest;
use App\Http\Requests\Automations\ModalAutomationTaskRequest;
use App\Http\Requests\Automations\UpdateAutomationTaskRequest;
use App\Http\Requests\Automations\CreateAutomationTaskRequest;
use App\Http\Requests\Automations\DeleteAutomationTaskRequest;
use App\Http\Requests\Automations\EnableAutomationTaskRequest;
use App\Http\Resources\Automations\AutomationTaskResourceCollection;
use App\Http\Requests\Automations\AutomationTaskFlowChartModalRequest;


class AutomationTaskController extends BaseAPIController
{

    public function list(ListAutomationTaskRequest $request)
    {
        $automationsTask = resolve(AutomationTaskService::class)->list($request->validatedDTO());
        $resource = (new AutomationTaskResourceCollection($automationsTask))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function modal(AutomationTask $automationTask, ModalAutomationTaskRequest $request)
    {
        $resource = (new AutomationTaskResource($automationTask))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function create(CreateAutomationTaskRequest $request)
    {
        $automationTask = resolve(AutomationTaskService::class)->create($request->validatedDTO());
        $resource = (new AutomationTaskResource($automationTask))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function update(AutomationTask $automationTask, UpdateAutomationTaskRequest $request)
    {
        $service = resolve(AutomationTaskService::class);
        $automationTaskUpdated = $service->update($automationTask, $request->validatedDTO());
        $resource = (new AutomationTaskResource($automationTaskUpdated))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function delete(AutomationTask $automationTask, DeleteAutomationTaskRequest $request)
    {
        $automationTaskDeleted = resolve(AutomationTaskService::class)->delete($automationTask);
        $resource = (new AutomationTaskResource($automationTaskDeleted))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function enable(AutomationTask $automationTask, EnableAutomationTaskRequest $request)
    {
        $automationTaskEnabled = resolve(AutomationTaskService::class)->enable($automationTask);
        $resource = (new AutomationTaskResource($automationTaskEnabled))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function disable(AutomationTask $automationTask, EnableAutomationTaskRequest $request)
    {
        $automationTaskDisabled = resolve(AutomationTaskService::class)->disable($automationTask);
        $resource = (new AutomationTaskResource($automationTaskDisabled))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function getFlowChartMarkdownModal(
        AutomationTask $automationTask,
        AutomationTaskFlowChartModalRequest $request
    ) {
        $markdown = resolve(AutomationTaskService::class)->getFlowChartMarkdownString($automationTask);
        return $this->getSuccessResponse(['markdown' => $markdown, 'automationTask' => $automationTask]);
    }


    // public function find(AutomationNewLead $automationNewLead, FindAutomationNewLeadRequest $request)
    // {
    //     $resource = (new AutomationNewLeadResource($automationNewLead))->loadOptionsFromRequest($request);
    //     return $this->getSuccessResponse($resource);
    // }
}
