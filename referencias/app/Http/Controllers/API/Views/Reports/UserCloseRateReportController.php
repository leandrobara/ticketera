<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\UserCloseRateReportExport;
use App\Services\API\Views\Reports\UserCloseRateReportService;
use App\Http\Requests\Views\Reports\UserCloseRateReportRequest;


class UserCloseRateReportController extends BaseAPIController
{

    public function list(UserCloseRateReportRequest $request)
    {
        $result = resolve(UserCloseRateReportService::class)->list($request->validated());
        return $this->getSuccessResponse($result);
    }


    public function export(UserCloseRateReportRequest $request)
    {
        $result = resolve(UserCloseRateReportService::class)->list($request->validated());
        return (new UserCloseRateReportExport($result))->download('reporte.xlsx');
    }

}
