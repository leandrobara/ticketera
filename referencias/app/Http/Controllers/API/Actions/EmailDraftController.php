<?php

namespace App\Http\Controllers\API\Actions;

use App\Models\Lead;
use App\Models\EmailDraft;
use App\Services\API\EmailDraftService;
use App\Http\Resources\EmailDraftResource;
use App\Http\Requests\GetEmailDraftRequest;
use App\Http\Requests\SaveEmailDraftRequest;
use App\Http\Requests\DeleteEmailDraftRequest;
use App\Http\Controllers\API\BaseAPIController;


class EmailDraftController extends BaseAPIController
{

    public function findOneByLead(Lead $lead, GetEmailDraftRequest $request)
    {
        $emailDraft = resolve(EmailDraftService::class)->findOneByLead($lead);
        $rs = $emailDraft ? (new EmailDraftResource($emailDraft))->loadOptionsFromRequest($request) : null;
        return $this->getSuccessResponse($rs);
    }


    public function saveLeadEmailDraft(Lead $lead, SaveEmailDraftRequest $request)
    {
        $emailDraft = resolve(EmailDraftService::class)->saveLeadEmailDraft($lead, $request->validated());
        return $this->getSuccessResponse((new EmailDraftResource($emailDraft))->loadOptionsFromRequest($request));
    }


    public function deleteByLead(Lead $lead, DeleteEmailDraftRequest $request)
    {
        $success = resolve(EmailDraftService::class)->deleteByLead($lead);
        return $this->getSuccessResponse($success);
    }

}
