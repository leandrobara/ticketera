<?php

namespace App\Http\Controllers\API\Integration;

use App\Models\Lead;
use Illuminate\Http\Request;
use App\Helpers\OpenAIHelper;
use App\Services\API\LeadService;
use App\Services\API\ClientService;
use App\Http\Resources\LeadResource;
use App\Services\API\LeadSaleService;
use App\Services\API\ProposalInfoService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Integration\ListLeadForMakeRequest;
use App\Services\API\Views\LeadService as ViewsLeadService;
use App\Http\Requests\Integration\SubscribeToMakeAppRequest;
use App\Http\Resources\Integration\LeadItemResourceCollection;
use App\Http\Requests\Integration\CreateLeadFromMakeAppRequest;
use App\Http\Requests\Integration\UpdateLeadFromMakeAppRequest;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Http\Requests\Integration\UnsubscribeFromMakeAppRequest;
use App\Http\Requests\Integration\CreateLeadSaleFromMakeAppRequest;
use App\Http\Requests\Integration\ChangeLeadStatusFromMakeAppRequest;
use App\Http\Requests\Integration\CreateProposalInfoFromMakeAppRequest;
use App\Http\Requests\Integration\ExtractLeadFromEmailUsingIAMakeRequest;


class MakeController extends BaseAPIController
{

    public function list(ListLeadForMakeRequest $req)
    {
        $leads = resolve(ViewsLeadService::class)->listForMakeApp($req->validated());
        return $this->getSuccessResponse(new LeadItemResourceCollection($leads));
    }


    public function doMakeAppBasicAuth(Request $req)
    {
        return $this->getSuccessResponse([]);
    }


    public function createLeadFromMakeApp(CreateLeadFromMakeAppRequest $req)
    {
        $lead = resolve(LeadService::class)->createFromExternalIntegrationApp($req->validatedDTO());
        $leadRs = (new LeadResource($lead))->setVisibleFields(['id']);
        return $this->getSuccessResponse($leadRs);
    }


    public function createLeadSaleFromMakeApp(Lead $lead, CreateLeadSaleFromMakeAppRequest $req)
    {
        resolve(LeadSaleService::class)->create($lead, $req->validatedAttributes());
        $leadRs = (new LeadResource($lead))->setVisibleFields(['id']);
        return $this->getSuccessResponse($leadRs);
    }


    public function createProposalInfoFromMakeApp(Lead $lead, CreateProposalInfoFromMakeAppRequest $req)
    {
        resolve(ProposalInfoService::class)->create($lead, $req->validatedAttributes());
        $leadRs = (new LeadResource($lead))->setVisibleFields(['id']);
        return $this->getSuccessResponse($leadRs);
    }


    public function updateLeadFromMakeApp(Lead $lead, UpdateLeadFromMakeAppRequest $req)
    {
        $updatedLead = resolve(LeadService::class)->updateFromApiIntegration($lead, $req->validatedDTO());
        return $this->getSuccessResponse((new LeadResource($updatedLead))->loadOptionsFromRequest($req));
    }


    public function changeStatusFromMakeApp(Lead $lead, ChangeLeadStatusFromMakeAppRequest $req)
    {
        resolve(ActionsLeadService::class)->changeStatus($lead, $req->getNewStatus());
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($req));
    }


    public function extractLeadFromEmailUsingIA(ExtractLeadFromEmailUsingIAMakeRequest $req)
    {
        $extractedLeadData = resolve(OpenAIHelper::class)->extractLeadFromEmail(
            $req->getEmailBody(), $req->getCustomPrompt(), $req->getCustomVariablesPrompts()
        );
        return response()->json($extractedLeadData);
    }


    public function subscribeToMakeApp(SubscribeToMakeAppRequest $req)
    {
        resolve(ClientService::class)->subscribeToMakeApp($req->input('triggerType'), $req->input('url'));
        return $this->getSuccessResponse([]);
    }


    public function unsubscribeFromMakeApp(UnsubscribeFromMakeAppRequest $req)
    {
        resolve(ClientService::class)->unsubscribeFromMakeApp($req->input('triggerType'));
        return $this->getSuccessResponse([]);
    }

}
