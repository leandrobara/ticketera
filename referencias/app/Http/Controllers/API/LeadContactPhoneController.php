<?php

namespace App\Http\Controllers\API;

use App\Models\LeadContact;
use App\Models\LeadContactPhone;
use App\Services\API\LeadContactPhoneService;
use App\Http\Requests\CreateLeadContactPhoneRequest;
use App\Http\Requests\DeleteLeadContactPhoneRequest;
use App\Http\Requests\UpdateLeadContactPhoneRequest;
use App\Http\Resources\LeadContactPhoneResource;


class LeadContactPhoneController extends BaseAPIController
{

    public function create(LeadContact $leadContact, CreateLeadContactPhoneRequest $request)
    {
        $leadContactPhone = resolve(LeadContactPhoneService::class)->create($leadContact, $request->validated());
        return $this->getSuccessResponse(
            (new LeadContactPhoneResource($leadContactPhone))->loadOptionsFromRequest($request)
        );
    }


    public function update(LeadContactPhone $leadContactPhone, UpdateLeadContactPhoneRequest $request)
    {
        $leadContactPhone = resolve(LeadContactPhoneService::class)->update($leadContactPhone, $request->validated());
        return $this->getSuccessResponse(
            (new LeadContactPhoneResource($leadContactPhone))->loadOptionsFromRequest($request)
        );
    }


    public function delete(LeadContactPhone $leadContactPhone, DeleteLeadContactPhoneRequest $request)
    {
        $leadContactPhone = resolve(LeadContactPhoneService::class)->delete($leadContactPhone);
        return $this->getSuccessResponse(
            (new LeadContactPhoneResource($leadContactPhone))->loadOptionsFromRequest($request)
        );
    }

}
