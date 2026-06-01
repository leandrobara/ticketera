<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\AcquisitionChannelReportExport;
use App\Http\Requests\Views\Reports\AcquisitionChannelReportRequest;
use App\Services\API\Views\Reports\AcquisitionChannelReportService;
use App\Http\Requests\Views\Reports\AcquisitionChannelChartReportRequest;


class AcquisitionChannelReportController extends BaseAPIController
{

    public function list(AcquisitionChannelReportRequest $request)
    {
        $response = resolve(AcquisitionChannelReportService::class)->list($request->validated());
        return $this->getSuccessResponse($response);
    }


    public function showChartReport(AcquisitionChannelChartReportRequest $request)
    {
        $response = resolve(AcquisitionChannelReportService::class)->list($request->validated());
        return $this->getSuccessResponse($response);
    }


    public function export(AcquisitionChannelReportRequest $request)
    {
        $response = resolve(AcquisitionChannelReportService::class)->list($request->validated());
        return (new AcquisitionChannelReportExport($response))->download('reporte.xlsx');
    }

}
