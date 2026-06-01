<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Views\Reports\UTMTraceReportService;
use App\Http\Requests\Views\Reports\AdwordTraceReportRequest;
use App\Http\Resources\Views\Reports\UTMTraceReport\UTMTraceReportResourceCollection;


class UTMTraceReportController extends BaseAPIController
{

    // Deprecado, no se usa.
    public function list(AdwordTraceReportRequest $request)
    {
        $response = resolve(UTMTraceReportService::class)->list($request->validated());
        return $this->getSuccessResponse(new UTMTraceReportResourceCollection($response));
    }

}
