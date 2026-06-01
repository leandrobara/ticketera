<?php

namespace App\Http\Controllers\Web\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class UserCloseRateReportController extends BaseController
{

    public function show(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.reports.user-close-rate.show', []);
    }

}