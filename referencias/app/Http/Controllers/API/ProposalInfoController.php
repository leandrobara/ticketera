<?php

namespace App\Http\Controllers\API;

use App\Models\Lead;
use App\Models\ProposalInfo;
use Illuminate\Http\Request;
use App\Services\API\ProposalInfoService;
use App\Http\Resources\ProposalInfoResource;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\CreateProposalInfoRequest;
use App\Http\Requests\UpdateProposalInfoRequest;
use App\Http\Requests\DeleteProposalInfoRequest;


class ProposalInfoController extends BaseAPIController
{

    public function getOne(Lead $lead, ProposalInfo $proposalInfo, Request $request)
    {
        return $this->getSuccessResponse((new ProposalInfoResource($proposalInfo))->loadOptionsFromRequest($request));
    }


    public function create(Lead $lead, CreateProposalInfoRequest $request)
    {
        $proposalInfo = resolve(ProposalInfoService::class)->create($lead, $request->validatedAttributes());
        return $this->getSuccessResponse((new ProposalInfoResource($proposalInfo))->loadOptionsFromRequest($request));
    }


    public function update(Lead $lead, ProposalInfo $proposalInfo, UpdateProposalInfoRequest $request)
    {
        $updatedProposalInfo = resolve(ProposalInfoService::class)->update(
            $proposalInfo, $request->validatedAttributes()
        );
        return $this->getSuccessResponse(
            (new ProposalInfoResource($updatedProposalInfo))->loadOptionsFromRequest($request)
        );
    }


    public function delete(Lead $lead, ProposalInfo $proposalInfo, DeleteProposalInfoRequest $request)
    {
        $deletedProposalInfo = resolve(ProposalInfoService::class)->delete($proposalInfo);
        return $this->getSuccessResponse(
            (new ProposalInfoResource($deletedProposalInfo))->loadOptionsFromRequest($request)
        );
    }

}
