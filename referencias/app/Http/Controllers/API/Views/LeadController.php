<?php

namespace App\Http\Controllers\API\Views;

use App\Models\Lead;
use App\Exports\LeadExport;
use App\Services\API\EventsLogService;
use App\Services\API\Views\LeadService;
use App\Services\API\TimelineEventsService;
use App\Http\Requests\Views\ListLeadRequest;
use App\Http\Requests\Views\LeadModalRequest;
use App\Http\Requests\Views\ExportLeadRequest;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\ListLeadPhonesRequest;
use App\Http\Requests\Views\QuickSearchLeadRequest;
use App\Http\Requests\Views\ExportLeadsByIdsRequest;
use App\Http\Requests\Views\TimelineEventsLeadRequest;
use App\Http\Requests\Views\ListLeadIdsByEmailRequest;
use App\Http\Requests\Views\ListLeadIdsByPhoneRequest;
use App\Services\API\WapBot\WapBotConversationService;
use App\Http\Resources\Views\LeadModal\LeadModalResource;
use App\Http\Resources\Views\LeadList\LeadListResourceCollection;
use App\Http\Requests\Views\WapBotConversationReferralDataRequest;
use App\Http\Resources\Views\LeadList\LeadListPhonesResourceCollection;
use App\Http\Resources\Views\TimelineEvents\TimelineEventsResourceCollection;
use App\Http\Resources\Views\LeadQuickSearch\LeadQuickSearchResourceCollection;


class LeadController extends BaseAPIController
{

    public function list(ListLeadRequest $req)
    {
        $leads = resolve(LeadService::class)->list($req->validated());
        return $this->getSuccessResponse(new LeadListResourceCollection($leads));
    }


    public function listIdsByEmail(ListLeadIdsByEmailRequest $req)
    {
        $ids = resolve(LeadService::class)->listIdsByClientAndEmail($req->client, $req->getEmail());
        return $this->getSuccessResponse($ids);
    }


    public function listIdsByPhone(ListLeadIdsByPhoneRequest $req)
    {
        $ids = resolve(LeadService::class)->listIdsByClientAndPhone($req->client, $req->getPhone());
        return $this->getSuccessResponse($ids);
    }


    public function export(ExportLeadRequest $req)
    {
        set_time_limit(300);
        ini_set('memory_limit', '2000M');
        ini_set('max_execution_time', 300);

        $leads = resolve(LeadService::class)->listToExport($req->validated());

        $leadsStatusEvents = resolve(EventsLogService::class)->findEventsFromManyLeads(
            $leads->pluck('id'),
            ['lead_created', 'lead_manually_created', 'lead_status_updated'],
            ['order' => 'created_date_asc']
        );
        $leadsStatusEvents = $leadsStatusEvents->groupBy('log.lead.id');
        return (new LeadExport($leads, $leadsStatusEvents))->download('reporte-prospectos.xlsx');
    }


    public function exportByIds(ExportLeadsByIdsRequest $req)
    {
        set_time_limit(300);
        ini_set('memory_limit', '2000M');
        ini_set('max_execution_time', 300);

        $leads = resolve(LeadService::class)->listToExport($req->validated());
        $leadsStatusEvents = resolve(EventsLogService::class)->findEventsFromManyLeads(
            $leads->pluck('id'),
            ['lead_created', 'lead_manually_created', 'lead_status_updated'],
            ['order' => 'created_date_asc']
        );
        $leadsStatusEvents = $leadsStatusEvents->groupBy('log.lead.id');
        return (new LeadExport($leads, $leadsStatusEvents))->download('reporte-prospectos.xlsx');
    }


    public function listIds(ListLeadRequest $req)
    {
        ini_set('memory_limit', '2000M');
        $ids = resolve(LeadService::class)->listIds($req->validated());
        return $this->getSuccessResponse($ids);
    }


    public function listPhones(ListLeadPhonesRequest $req)
    {
        ini_set('memory_limit', '2000M');
        $leads = resolve(LeadService::class)->listToListPhones($req->validated());
        return $this->getSuccessResponse(new LeadListPhonesResourceCollection($leads));
    }


    public function modal(Lead $lead, LeadModalRequest $req)
    {
        $rs = (new LeadModalResource($lead))->additional(['loginUser' => $req->user]);
        return $this->getSuccessResponse($rs);
    }


    public function timelineEvents(Lead $lead, TimelineEventsLeadRequest $req)
    {
        $timelineEvents = resolve(TimelineEventsService::class)->findTimelineEventsByLead($lead);
        return $this->getSuccessResponse(new TimelineEventsResourceCollection($timelineEvents));
    }


    public function wapBotConversationReferralData(Lead $lead, WapBotConversationReferralDataRequest $req)
    {
        $referralData = null;
        if ($lead->is_wap_bot_chat) {
            $referralData = resolve(WapBotConversationService::class)->findReferralDataByLead($lead);
        }
        return $this->getSuccessResponse(['referralData' => $referralData]);
    }


    public function quickSearch(QuickSearchLeadRequest $req)
    {
        $leadDocuments = resolve(LeadService::class)->quickSearch($req->validated());
        return $this->getSuccessResponse(new LeadQuickSearchResourceCollection($leadDocuments));
    }

}
