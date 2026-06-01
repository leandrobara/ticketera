<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Helpers\SystemHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\UTMKeywordsTraceReportExport;
use App\Services\API\Views\Reports\UTMKeywordsTraceReportService;
use App\Http\Requests\Views\Reports\UTMKeywordsTraceListReportRequest;


class UTMKeywordsTraceReportController extends BaseAPIController
{

    public function list(UTMKeywordsTraceListReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $response = resolve(UTMKeywordsTraceReportService::class)->list($req->client, $req->validated());
        return $this->getSuccessResponse($response);
    }


    public function export(UTMKeywordsTraceListReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $response = resolve(UTMKeywordsTraceReportService::class)->list($req->client, $req->validated());
        return (new UTMKeywordsTraceReportExport($response))->download('report.xlsx');
    }

}
