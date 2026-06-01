<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\SalesHistoryReportExport;
use App\Http\Requests\Views\Reports\SalesHistoryListRequest;
use App\Services\API\Views\Reports\SalesHistoryReportService;
use App\Http\Requests\Views\Reports\SalesHistorySummaryRequest;
use App\Http\Requests\Views\Reports\SalesHistorySummaryChartReportRequest;
use App\Http\Resources\Views\Reports\SalesHistory\SalesHistoryResourceCollection;


class SalesHistoryReportController extends BaseAPIController
{

    public function list(SalesHistoryListRequest $request)
    {
        $response = resolve(SalesHistoryReportService::class)->list($request->validated());
        return $this->getSuccessResponse((new SalesHistoryResourceCollection($response)));
    }


    public function summary(SalesHistorySummaryRequest $request)
    {
        $response = resolve(SalesHistoryReportService::class)->summary($request->validated());
        return $this->getSuccessResponse($response);
    }


    public function showChartReport(SalesHistorySummaryChartReportRequest $request)
    {
        $response = resolve(SalesHistoryReportService::class)->summary($request->validated());
        return $this->getSuccessResponse($response);
    }


    public function export(SalesHistoryListRequest $request)
    {
        $response = resolve(SalesHistoryReportService::class)->export($request->validated());
        return (new SalesHistoryReportExport($response))->download('reporte.xlsx');
    }

}
