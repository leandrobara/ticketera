<?php

namespace App\Http\Controllers\API\WAutomations;

use App\Models\WAutomationSequence;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WAutomations\WAutomationSequenceService;
use App\Http\Resources\WAutomations\WAutomationSequenceResource;
use App\Http\Requests\WAutomations\SaveWAutomationSequenceRequest;
use App\Http\Requests\WAutomations\DeleteWAutomationSequenceRequest;
use App\Http\Requests\WAutomations\CreateWAutomationSequenceRequest;
use App\Http\Requests\WAutomations\UpdateWAutomationSequenceRequest;
use App\Http\Requests\WAutomations\EnableWAutomationSequenceRequest;


class WAutomationSequenceController extends BaseAPIController
{

    public function save(SaveWAutomationSequenceRequest $req)
    {
        $wAutomationSequence = resolve(WAutomationSequenceService::class)->save($req->validatedDTO());
        $resource = (new WAutomationSequenceResource($wAutomationSequence))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($resource);
    }


    public function create(CreateWAutomationSequenceRequest $req)
    {
        $wAutomationSequence = resolve(WAutomationSequenceService::class)->create($req->validatedDTO());
        $resource = (new WAutomationSequenceResource($wAutomationSequence))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($resource);
    }


    public function update(WAutomationSequence $wAutomationSequence, UpdateWAutomationSequenceRequest $req)
    {
        $wAutomationSequence = resolve(WAutomationSequenceService::class)->update(
            $wAutomationSequence, $req->validatedDTO()
        );
        $resource = (new WAutomationSequenceResource($wAutomationSequence))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($resource);
    }


    public function delete(WAutomationSequence $wAutomationSequence, DeleteWAutomationSequenceRequest $req)
    {
        $wAutomationSequence = resolve(WAutomationSequenceService::class)->delete($wAutomationSequence);
        $resource = (new WAutomationSequenceResource($wAutomationSequence))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($resource);
    }


    public function enable(WAutomationSequence $wAutomationSequence, EnableWAutomationSequenceRequest $req)
    {
        $wAutomationSequence = resolve(WAutomationSequenceService::class)->enable($wAutomationSequence);
        $resource = (new WAutomationSequenceResource($wAutomationSequence))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($resource);
    }


    public function disable(WAutomationSequence $wAutomationSequence, EnableWAutomationSequenceRequest $req)
    {
        $wAutomationSequence = resolve(WAutomationSequenceService::class)->disable($wAutomationSequence);
        $resource = (new WAutomationSequenceResource($wAutomationSequence))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($resource);
    }

}
