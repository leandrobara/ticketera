<?php

namespace App\Http\Controllers\API\Automations;

use App\Models\AutomationNewLead;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Automations\AutomationNewLeadService;
use App\Http\Resources\Automations\AutomationNewLeadResource;
use App\Http\Requests\Automations\FindAutomationNewLeadRequest;
use App\Http\Requests\Automations\ListAutomationNewLeadRequest;
use App\Http\Requests\Automations\CreateAutomationNewLeadRequest;
use App\Http\Requests\Automations\UpdateAutomationNewLeadRequest;
use App\Http\Requests\Automations\DeleteAutomationNewLeadRequest;
use App\Http\Requests\Automations\AutomationNewLeadFlowChartsRequest;
use App\Http\Resources\Automations\AutomationNewLeadResourceCollection;
use App\Http\Requests\Automations\AutomationNewLeadFlowChartModalRequest;


class AutomationNewLeadController extends BaseAPIController
{

    public function list(ListAutomationNewLeadRequest $request)
    {
        $automations = resolve(AutomationNewLeadService::class)->list($request->validatedDTO());
        $resource = (new AutomationNewLeadResourceCollection($automations))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function find(AutomationNewLead $automationNewLead, FindAutomationNewLeadRequest $request)
    {
        $resource = (new AutomationNewLeadResource($automationNewLead))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function getFlowChartMarkdownModal(
        AutomationNewLead $automationNewLead,
        AutomationNewLeadFlowChartModalRequest $request
    ) {
        $markdown = resolve(AutomationNewLeadService::class)->getFlowChartMarkdownString($automationNewLead);
        return $this->getSuccessResponse(['markdown' => $markdown, 'automationNewLead' => $automationNewLead]);
    }


    public function create(CreateAutomationNewLeadRequest $request)
    {
        $automationNewLead = resolve(AutomationNewLeadService::class)->create($request->validatedDTO());
        $resource = (new AutomationNewLeadResource($automationNewLead))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function update(AutomationNewLead $automationNewLead, UpdateAutomationNewLeadRequest $request)
    {
        $service = resolve(AutomationNewLeadService::class);
        $automationNewLead = $service->update($automationNewLead, $request->validatedDTO());
        $resource = (new AutomationNewLeadResource($automationNewLead))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function delete(AutomationNewLead $automationNewLead, DeleteAutomationNewLeadRequest $request)
    {
        $service = resolve(AutomationNewLeadService::class);
        $automationNewLead = $service->delete($automationNewLead);
        $resource = (new AutomationNewLeadResource($automationNewLead))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }

}
