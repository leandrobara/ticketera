<?php

namespace App\Http\Controllers\API\Automations;

use App\Models\AutomationEmailSendStep;
use App\Models\AutomationEmailSend;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Automations\AutomationEmailSendStepService;
use App\Http\Requests\Automations\CreateAutomationEmailSendStepRequest;
use App\Http\Requests\Automations\DeleteAutomationEmailSendStepRequest;
use App\Http\Requests\Automations\UpdateAutomationEmailSendStepRequest;
use App\Http\Resources\Automations\AutomationEmailSendStepResource;


class AutomationEmailSendStepController extends BaseAPIController
{

    public function create(AutomationEmailSend $automationEmailSend, CreateAutomationEmailSendStepRequest $req)
    {
        $automationEmailSendStep = resolve(AutomationEmailSendStepService::class)->create($req->validatedDTO());
        $resource = (new AutomationEmailSendStepResource($automationEmailSendStep))->loadOptionsFromRequest($req);
        return $this->getsuccessresponse($resource);
    }


    public function update(
        AutomationEmailSend $automationEmailSend,
        AutomationEmailSendStep $automationEmailSendStep,
        UpdateAutomationEmailSendStepRequest $req
    ) {
        $automationEmailSendStep = resolve(AutomationEmailSendStepService::class)->update(
            $automationEmailSendStep, $req->validatedDTO()
        );
        $resource = (new AutomationEmailSendStepResource($automationEmailSendStep))->loadOptionsFromRequest($req);
        return $this->getsuccessresponse($resource);
    }


    public function delete(
        AutomationEmailSend $automationEmailSend,
        AutomationEmailSendStep $automationEmailSendStep,
        DeleteAutomationEmailSendStepRequest $req
    ) {
        $automationEmailSendStep = resolve(AutomationEmailSendStepService::class)->delete($automationEmailSendStep);
        $resource = (new AutomationEmailSendStepResource($automationEmailSendStep))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($resource);
    }

}
