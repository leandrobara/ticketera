<?php

namespace App\Http\Controllers\API\Views\ClientyConfigurations;

use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\ClientyConfigurations\ClientUsageReportExport;
use App\Exports\Reports\ClientyConfigurations\AllClientsUsageReportExport;
use App\Http\Requests\Views\ClientyConfigurations\ClientUsageReportListRequest;
use App\Services\API\Views\Reports\ClientyConfigurations\ClientUsageReportService;
use App\Http\Requests\Views\ClientyConfigurations\ClientUsageAllClientsReportListRequest;
use App\Http\Requests\Views\ClientyConfigurations\ClientUsageAllClientsReportExportRequest;


class ClientUsageReportController extends BaseAPIController
{

    public function list(ClientUsageReportListRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $report = resolve(ClientUsageReportService::class)->userLevelReport($req->reportClient, $req->validated());
        return $this->getSuccessResponse($report);
    }


    public function export(ClientUsageReportListRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $report = resolve(ClientUsageReportService::class)->userLevelReport($req->reportClient, $req->validated());
        return (new ClientUsageReportExport($report))->download('clienty-reporte-actividad-cliente.xlsx');
    }


    public function allClientsList(ClientUsageAllClientsReportListRequest $req)
    {
        SystemHelper::setTimeLimit(180);
        SystemHelper::setMemoryLimitMB(512);
        $report = resolve(ClientUsageReportService::class)->allClientsReport($req->validated());
        return $this->getSuccessResponse($report);
    }


    public function allClientsExport(ClientUsageAllClientsReportExportRequest $req)
    {
        SystemHelper::setTimeLimit(180);
        SystemHelper::setMemoryLimitMB(512);
        $report = resolve(ClientUsageReportService::class)->allClientsReport($req->validated());
        return (new AllClientsUsageReportExport(
            $report, $req->dateStart, $req->dateEnd
        ))->download('clienty-reporte-actividad-clientes.xlsx');
    }

}
