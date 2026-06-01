<?php

namespace App\Http\Controllers\API;

use App\Models\Lead;
use App\Services\API\LeadService;
use App\Http\Resources\LeadResource;
use App\Http\Requests\GetLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Requests\CreateLeadRequest;
use App\Http\Controllers\API\BaseAPIController;


class LeadController extends BaseAPIController
{

    public function createManual(CreateLeadRequest $request)
    {
        $lead = resolve(LeadService::class)->createManual($request->validatedDTO());
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($request));
    }


    public function update(Lead $lead, UpdateLeadRequest $request)
    {
        $lead = resolve(LeadService::class)->update($lead, $request->validatedAttributes());
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($request));
    }


    public function getOne(Lead $lead, GetLeadRequest $request)
    {
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($request));
    }

}
