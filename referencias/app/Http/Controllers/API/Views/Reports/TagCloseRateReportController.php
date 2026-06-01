<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Helpers\SystemHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\TagCloseRateReportExport;
use App\Services\API\Views\Reports\TagCloseRateReportService;
use App\Http\Requests\Views\Reports\TagCloseRateReportRequest;
use App\Http\Requests\Views\Reports\TagCloseRateChartReportRequest;


class TagCloseRateReportController extends BaseAPIController
{

    public function list(TagCloseRateReportRequest $request)
    {
        SystemHelper::setMemoryLimitMB(500);
        $result = resolve(TagCloseRateReportService::class)->list($request->validated());
        return $this->getSuccessResponse($result);
    }


    public function showChartReport(TagCloseRateChartReportRequest $request)
    {
        SystemHelper::setMemoryLimitMB(500);
        $result = resolve(TagCloseRateReportService::class)->list($request->validated());
        return $this->getSuccessResponse($result);
    }


    public function export(TagCloseRateReportRequest $request)
    {
        SystemHelper::setMemoryLimitMB(500);
        $result = resolve(TagCloseRateReportService::class)->list($request->validated());
        return (new TagCloseRateReportExport($result))->download('clienty-reporte-cierre-segun-etiquetas.xlsx');
    }

}
