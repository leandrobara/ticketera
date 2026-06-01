<?php

namespace App\Http\Controllers\API\WAutomations;

use App\Models\WAutomationSequence;
use App\Models\WAutomationSequenceStep;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WAutomations\WAutomationSequenceStepService;
use App\Http\Resources\WAutomations\WAutomationSequenceStepResource;
use App\Http\Requests\WAutomations\CreateWAutomationSequenceStepRequest;
use App\Http\Requests\WAutomations\DeleteWAutomationSequenceStepRequest;
use App\Http\Requests\WAutomations\UpdateWAutomationSequenceStepRequest;


class WAutomationSequenceStepController extends BaseAPIController
{

    public function create(WAutomationSequence $wAutomationSequence, CreateWAutomationSequenceStepRequest $req)
    {
        $wAutomationSequenceStep = resolve(WAutomationSequenceStepService::class)->create($req->validatedDTO());
        $resource = (new WAutomationSequenceStepResource($wAutomationSequenceStep))->loadOptionsFromRequest($req);
        return $this->getsuccessresponse($resource);
    }


    public function update(
        WAutomationSequence $wAutomationSequence,
        WAutomationSequenceStep $wAutomationSequenceStep,
        UpdateWAutomationSequenceStepRequest $req
    ) {
        $wAutomationSequenceStep = resolve(WAutomationSequenceStepService::class)->update(
            $wAutomationSequenceStep, $req->validatedDTO()
        );
        $resource = (new WAutomationSequenceStepResource($wAutomationSequenceStep))->loadOptionsFromRequest($req);
        return $this->getsuccessresponse($resource);
    }


    public function delete(
        WAutomationSequence $wAutomationSequence,
        WAutomationSequenceStep $wAutomationSequenceStep,
        DeleteWAutomationSequenceStepRequest $req
    ) {
        $wAutomationSequenceStep = resolve(WAutomationSequenceStepService::class)->delete($wAutomationSequenceStep);
        $resource = (new WAutomationSequenceStepResource($wAutomationSequenceStep))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($resource);
    }

}
