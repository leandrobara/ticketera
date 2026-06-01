<?php

namespace App\Http\Controllers\API\Integration;

use App\Models\Lead;
use App\Models\Task;
use Illuminate\Http\Request;
use App\Helpers\OpenAIHelper;
use App\Services\API\LeadService;
use App\Services\API\ClientService;
use App\Http\Resources\LeadResource;
use App\Helpers\IntegrationApiHelper;
use App\Services\API\LeadSaleService;
use App\Services\API\ProposalInfoService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\Integration\WebhookLeadResource;
use App\Http\Resources\Integration\WebhookTaskResource;
use App\Http\Requests\Integration\ListLeadForZapierRequest;
use App\Services\API\Views\LeadService as ViewsLeadService;
use App\Services\API\Views\TaskService as ViewsTaskService;
use App\Http\Resources\Integration\LeadItemResourceCollection;
use App\Http\Requests\Integration\SubscribeToZapierAppRequest;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Http\Requests\Integration\CreateLeadFromZapierAppRequest;
use App\Http\Requests\Integration\ListForZapierAppPollingRequest;
use App\Http\Requests\Integration\UpdateLeadFromZapierAppRequest;
use App\Http\Requests\Integration\UnsubscribeFromZapierAppRequest;
use App\Http\Requests\Integration\CreateLeadFromZapierWebhookRequest;
use App\Http\Requests\Integration\CreateLeadSaleFromZapierAppRequest;
use App\Http\Requests\Integration\ChangeLeadStatusFromZapierAppRequest;
use App\Http\Requests\Integration\CreateProposalInfoFromZapierAppRequest;
use App\Http\Requests\Integration\ExtractLeadFromEmailUsingIAZapierRequest;


class ZapierController extends BaseAPIController
{

    public function list(ListLeadForZapierRequest $req)
    {
        $leads = resolve(ViewsLeadService::class)->listForZapierApp($req->validated());
        return $this->getSuccessResponse(new LeadItemResourceCollection($leads));
    }


    public function doZapierAppBasicAuth(Request $req)
    {
        return $this->getSuccessResponse([]);
    }


    public function createLeadFromZapierApp(CreateLeadFromZapierAppRequest $req)
    {
        $lead = resolve(LeadService::class)->createFromExternalIntegrationApp($req->validatedDTO());
        $leadRs = (new LeadResource($lead))->setVisibleFields(['id']);
        return $this->getSuccessResponse($leadRs);
    }


    public function createLeadSaleFromZapierApp(Lead $lead, CreateLeadSaleFromZapierAppRequest $req)
    {
        resolve(LeadSaleService::class)->create($lead, $req->validatedAttributes());
        $leadRs = (new LeadResource($lead))->setVisibleFields(['id', 'sales_date']);
        return $this->getSuccessResponse($leadRs);
    }


    public function createProposalInfoFromZapierApp(Lead $lead, CreateProposalInfoFromZapierAppRequest $req)
    {
        resolve(ProposalInfoService::class)->create($lead, $req->validatedAttributes());
        $leadRs = (new LeadResource($lead))->setVisibleFields(['id']);
        return $this->getSuccessResponse($leadRs);
    }


    public function updateLeadFromZapierApp(Lead $lead, UpdateLeadFromZapierAppRequest $req)
    {
        $updatedLead = resolve(LeadService::class)->updateFromApiIntegration($lead, $req->validatedDTO());
        $leadRs = (new LeadResource($updatedLead))->setVisibleFields(['id']);
        return $this->getSuccessResponse($leadRs);
    }


    public function changeStatusFromZapierApp(Lead $lead, ChangeLeadStatusFromZapierAppRequest $req)
    {
        resolve(ActionsLeadService::class)->changeStatus($lead, $req->getNewStatus());
        return $this->getSuccessResponse((new LeadResource($lead))->loadOptionsFromRequest($req));
    }

    
    public function extractLeadFromEmailUsingIA(ExtractLeadFromEmailUsingIAZapierRequest $req)
    {
        $extractedLeadData = resolve(OpenAIHelper::class)->extractLeadFromEmail(
            $req->getEmailBody(), $req->getCustomPrompt(), $req->getCustomVariablesPrompts()
        );
        return response()->json($extractedLeadData);
    }


    public function subscribeToZapierApp(SubscribeToZapierAppRequest $req)
    {
        resolve(ClientService::class)->subscribeToZapierApp($req->input('triggerType'), $req->input('hookUrl'));
        return $this->getSuccessResponse([]);
    }


    public function unsubscribeFromZapierApp(UnsubscribeFromZapierAppRequest $req)
    {
        resolve(ClientService::class)->unsubscribeFromZapierApp($req->input('triggerType'));
        return $this->getSuccessResponse([]);
    }


    public function listLeadForZapierAppPolling(ListForZapierAppPollingRequest $req)
    {
        $leads = resolve(ViewsLeadService::class)->listForZapierAppPolling($req->input('triggerType'));
        $response = $leads->map(function (Lead $lead) use ($req) {
            return (new WebhookLeadResource($lead, $req->input('triggerType')))->toArray();
        })->values()->toArray();
        // \Log::info(print_r($response, true));
        return $response;
    }


    public function listTaskForZapierAppPolling(ListForZapierAppPollingRequest $req)
    {
        $tasks = resolve(ViewsTaskService::class)->listForZapierAppPolling($req->input('triggerType'));
        $response = $tasks->map(function (Task $task) use ($req) {
            return (new WebhookTaskResource($task, $req->input('triggerType')))->toArray();
        })->values()->toArray();
        return $response;
    }

}
