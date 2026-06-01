<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Helpers\SystemHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\UTMAdGroupTraceReportExport;
use App\Services\API\Views\Reports\UTMAdGroupTraceReportService;
use App\Http\Requests\Views\Reports\UTMAdGroupTraceListReportRequest;


class UTMAdGroupTraceReportController extends BaseAPIController
{

    public function list(UTMAdGroupTraceListReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $response = resolve(UTMAdGroupTraceReportService::class)->list($req->client, $req->validated());
        return $this->getSuccessResponse($response);
    }


    public function export(UTMAdGroupTraceListReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $response = resolve(UTMAdGroupTraceReportService::class)->list($req->client, $req->validated());
        return (new UTMAdGroupTraceReportExport($response))->download('report.xlsx');
    }

}
