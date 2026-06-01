<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\SalesFunnelReportExport;
use App\Services\API\Views\Reports\SalesFunnelReportService;
use App\Http\Requests\Views\Reports\SalesFunnelReportListRequest;
use App\Http\Requests\Views\Reports\SalesFunnelReportAverageTicketAmountRequest;


class SalesFunnelReportController extends BaseAPIController
{

    public function list(SalesFunnelReportListRequest $request)
    {
        $response = resolve(SalesFunnelReportService::class)->list($request->validated());
        return $this->getSuccessResponse($response);
    }


    public function getAverageTicketAmount(SalesFunnelReportAverageTicketAmountRequest $request)
    {
        $response = resolve(SalesFunnelReportService::class)->getAverageTicketAmount($request->validated());
        return $this->getSuccessResponse($response);
    }


    public function export(SalesFunnelReportListRequest $request)
    {
        $averageTicket = $request->getFilterAverageTicket();
        $salesFunnelReport = resolve(SalesFunnelReportService::class)->export($request->validated());
        return (new SalesFunnelReportExport($averageTicket, $salesFunnelReport))->download('reporte.xlsx');
    }

}
