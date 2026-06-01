<?php

namespace App\Http\Controllers\API\Automations;

use App\Models\AutomationEmailSend;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Automations\AutomationEmailSendService;
use App\Http\Resources\Automations\AutomationEmailSendResource;
use App\Http\Requests\Automations\SaveAutomationEmailSendRequest;
use App\Http\Requests\Automations\DeleteAutomationEmailSendRequest;
use App\Http\Requests\Automations\CreateAutomationEmailSendRequest;
use App\Http\Requests\Automations\UpdateAutomationEmailSendRequest;
use App\Http\Requests\Automations\EnableAutomationEmailSendRequest;


class AutomationEmailSendController extends BaseAPIController
{

    public function save(SaveAutomationEmailSendRequest $request)
    {
        $automationEmailSend = resolve(AutomationEmailSendService::class)->save($request->validatedDTO());
        $resource = (new AutomationEmailSendResource($automationEmailSend))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function create(CreateAutomationEmailSendRequest $request)
    {
        $automationEmailSend = resolve(AutomationEmailSendService::class)->create($request->validatedDTO());
        $resource = (new AutomationEmailSendResource($automationEmailSend))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function update(AutomationEmailSend $automationEmailSend, UpdateAutomationEmailSendRequest $request)
    {
        $automationEmailSend = resolve(AutomationEmailSendService::class)->update(
            $automationEmailSend,
            $request->validatedDTO()
        );
        $resource = (new AutomationEmailSendResource($automationEmailSend))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function delete(AutomationEmailSend $automationEmailSend, DeleteAutomationEmailSendRequest $request)
    {
        $automationEmailSend = resolve(AutomationEmailSendService::class)->delete($automationEmailSend);
        $resource = (new AutomationEmailSendResource($automationEmailSend))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function enable(AutomationEmailSend $automationEmailSend, EnableAutomationEmailSendRequest $request)
    {
        $automationEmailSend = resolve(AutomationEmailSendService::class)->enable($automationEmailSend);
        $resource = (new AutomationEmailSendResource($automationEmailSend))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }

    public function disable(AutomationEmailSend $automationEmailSend, EnableAutomationEmailSendRequest $request)
    {
        $automationEmailSend = resolve(AutomationEmailSendService::class)->disable($automationEmailSend);
        $resource = (new AutomationEmailSendResource($automationEmailSend))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }

}
