<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Models\Lead;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Exports\Reports\UsersActivityReportExport;
use App\Services\API\Views\Reports\UsersActivityReportService;
use App\Http\Requests\Views\Reports\UsersActivityReportRequest;
use App\Http\Resources\Views\LeadQuickSearch\LeadQuickSearchResourceCollection;


class UsersActivityReportController extends BaseAPIController
{

    public function list(UsersActivityReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $usersActivity = resolve(UsersActivityReportService::class)->list($req->client, $req->validated());
        return $this->getSuccessResponse($usersActivity);
    }


    public function export(UsersActivityReportRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $usersActivity = resolve(UsersActivityReportService::class)->list($req->client, $req->validated());
        return (new UsersActivityReportExport($usersActivity))->download('clienty-reporte-actividad.xlsx');
    }

}
