<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Helpers\SystemHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\UTMCampaignTraceReportExport;
use App\Services\API\Views\Reports\UTMCampaignTraceReportService;
use App\Http\Requests\Views\Reports\UTMCampaignTraceListReportRequest;


class UTMCampaignTraceReportController extends BaseAPIController
{

    public function list(UTMCampaignTraceListReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $response = resolve(UTMCampaignTraceReportService::class)->list($req->client, $req->validated());
        return $this->getSuccessResponse($response);
    }


    public function export(UTMCampaignTraceListReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $response = resolve(UTMCampaignTraceReportService::class)->list($req->client, $req->validated());
        return (new UTMCampaignTraceReportExport($response))->download('report.xlsx');
    }

}
