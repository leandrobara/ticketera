<?php

namespace App\Http\Controllers\API;

use App\Models\Lead;
use App\Models\LeadContact;
use App\Services\API\LeadContactService;
use App\Http\Resources\LeadContactResource;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\CreateLeadContactRequest;
use App\Http\Requests\UpdateLeadContactRequest;
use App\Http\Requests\DeleteLeadContactRequest;


class LeadContactController extends BaseAPIController
{

    public function create(Lead $lead, CreateLeadContactRequest $request)
    {
        $leadContact = resolve(LeadContactService::class)->create($lead, $request->validatedAttributes());
        return $this->getSuccessResponse((new LeadContactResource($leadContact))->loadOptionsFromRequest($request));
    }


    public function update(LeadContact $leadContact, UpdateLeadContactRequest $request)
    {
        $leadContact = resolve(LeadContactService::class)->update($leadContact, $request->validatedAttributes());
        return $this->getSuccessResponse((new LeadContactResource($leadContact))->loadOptionsFromRequest($request));
    }


    public function delete(LeadContact $leadContact, DeleteLeadContactRequest $request)
    {
        $leadContact = resolve(LeadContactService::class)->delete($leadContact);
        return $this->getSuccessResponse((new LeadContactResource($leadContact))->loadOptionsFromRequest($request));
    }

}
