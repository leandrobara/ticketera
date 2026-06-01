<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Helpers\SystemHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\SentProposalReportExport;
use App\Http\Requests\Views\ListProposalInfoRequest;
use App\Http\Requests\Views\SummaryProposalInfoRequest;
use App\Services\API\Views\Reports\SentProposalReportService;
use App\Http\Requests\Views\Reports\SentProposalSummaryChartReportRequest;
use App\Http\Resources\Views\Reports\SentProposal\SentProposalReportResourceCollection;


class SentProposalReportController extends BaseAPIController
{

    public function list(ListProposalInfoRequest $request)
    {
        $response = resolve(SentProposalReportService::class)->list($request->validated());
        return $this->getSuccessResponse((new SentProposalReportResourceCollection($response)));
    }


    public function summary(SummaryProposalInfoRequest $request)
    {
        $response = resolve(SentProposalReportService::class)->summary($request->validated());
        return $this->getSuccessResponse($response);
    }


    public function showChartReport(SentProposalSummaryChartReportRequest $request)
    {
        $response = resolve(SentProposalReportService::class)->summary($request->validated());
        return $this->getSuccessResponse($response);
    }


    public function export(ListProposalInfoRequest $request)
    {
        SystemHelper::setTimeLimit(120);
        SystemHelper::setMemoryLimitMB(512);
        $response = resolve(SentProposalReportService::class)->export($request->validated());
        return (new SentProposalReportExport($response))->download('reporte.xlsx');
    }

}
