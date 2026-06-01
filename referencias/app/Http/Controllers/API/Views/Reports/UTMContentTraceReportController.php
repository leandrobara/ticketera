<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Helpers\SystemHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\UTMContentTraceReportExport;
use App\Services\API\Views\Reports\UTMContentTraceReportService;
use App\Http\Requests\Views\Reports\UTMContentTraceListReportRequest;


class UTMContentTraceReportController extends BaseAPIController
{

    public function list(UTMContentTraceListReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $response = resolve(UTMContentTraceReportService::class)->list($req->client, $req->validated());
        return $this->getSuccessResponse($response);
    }


    public function export(UTMContentTraceListReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $response = resolve(UTMContentTraceReportService::class)->list($req->client, $req->validated());
        return (new UTMContentTraceReportExport($response))->download('report.xlsx');
    }

}
