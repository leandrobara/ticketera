<?php

namespace App\Http\Controllers\API\Integration;

use App\Models\Lead;
use Illuminate\Http\Request;
use App\Services\API\LeadService;
use App\Services\API\ClientService;
use App\Http\Resources\LeadResource;
use App\Helpers\IntegrationApiHelper;
use App\Services\API\LeadSaleService;
use App\Services\API\ProposalInfoService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Integration\ListLeadRequest;
use App\Http\Requests\Integration\UpdateLeadRequest;
use App\Http\Requests\Integration\CreateLeadRequest;
use App\Http\Requests\Integration\SendToWebhookRequest;
use App\Http\Requests\Integration\CreateLeadSaleRequest;
use App\Http\Requests\Integration\ChangeLeadStatusRequest;
use App\Http\Requests\Integration\CreateProposalInfoRequest;
use App\Services\API\Views\LeadService as ViewsLeadService;
use App\Http\Resources\Integration\LeadItemResourceCollection;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Http\Requests\Integration\SubscribeFromZapierAppRequest;
use App\Http\Requests\Integration\CreateLeadFromZapierAppRequest;
use App\Http\Requests\Integration\UnsubscribeFromZapierAppRequest;
use App\Http\Requests\Integration\CreateLeadFromZapierWebhookRequest;


class LeadController extends BaseAPIController
{

    public function list(ListLeadRequest $req)
    {
        $leads = resolve(ViewsLeadService::class)->list($req->validated());
        return $this->getSuccessResponse(new LeadItemResourceCollection($leads));
    }


    public function create(CreateLeadRequest $req)
    {
        $lead = resolve(LeadService::class)->createFromApiIntegration($req->validatedDTO());
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($req));
    }


    public function update(Lead $lead, UpdateLeadRequest $req)
    {
        $updatedLead = resolve(LeadService::class)->updateFromApiIntegration($lead, $req->validatedDTO());
        return $this->getSuccessResponse((new LeadResource($updatedLead))->loadOptionsFromRequest($req));
    }


    public function changeStatus(Lead $lead, ChangeLeadStatusRequest $req)
    {
        resolve(ActionsLeadService::class)->changeStatus($lead, $req->getNewStatus());
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($req));
    }


    public function createLeadSale(Lead $lead, CreateLeadSaleRequest $req)
    {
        $leadSale = resolve(LeadSaleService::class)->create($lead, $req->validatedAttributes());
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($req));
    }


    public function createProposalInfo(Lead $lead, CreateProposalInfoRequest $req)
    {
        $proposalInfo = resolve(ProposalInfoService::class)->create($lead, $req->validatedAttributes());
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($req));
    }


    public function createFromZapierWebhook(CreateLeadFromZapierWebhookRequest $req)
    {
        $lead = resolve(LeadService::class)->createFromApiIntegration($req->validatedDTO());
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($req));
    }

}
