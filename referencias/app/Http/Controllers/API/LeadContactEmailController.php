<?php

namespace App\Http\Controllers\API;

use App\Models\LeadContact;
use App\Models\LeadContactEmail;
use App\Services\API\LeadContactEmailService;
use App\Http\Resources\LeadContactEmailResource;
use App\Http\Requests\CreateLeadContactEmailRequest;
use App\Http\Requests\DeleteLeadContactEmailRequest;
use App\Http\Requests\UpdateLeadContactEmailRequest;


class LeadContactEmailController extends BaseAPIController
{

    public function create(LeadContact $leadContact, CreateLeadContactEmailRequest $request)
    {
        $leadContactEmail = resolve(LeadContactEmailService::class)->create($leadContact, $request->validated());
        return $this->getSuccessResponse(
            (new LeadContactEmailResource($leadContactEmail))->loadOptionsFromRequest($request)
        );
    }


    public function update(LeadContactEmail $leadContactEmail, UpdateLeadContactEmailRequest $request)
    {
        $leadContactEmail = resolve(LeadContactEmailService::class)->update($leadContactEmail, $request->validated());
        return $this->getSuccessResponse(
            (new LeadContactEmailResource($leadContactEmail))->loadOptionsFromRequest($request)
        );
    }


    public function delete(LeadContactEmail $leadContactEmail, DeleteLeadContactEmailRequest $request)
    {
        $leadContactEmail = resolve(LeadContactEmailService::class)->delete($leadContactEmail);
        return $this->getSuccessResponse(
            (new LeadContactEmailResource($leadContactEmail))->loadOptionsFromRequest($request)
        );
    }
}
