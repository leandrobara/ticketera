<?php

namespace App\Http\Controllers\Web\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class AcquisitionChannelsReportController extends BaseController
{

    public function show(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.reports.acquisition-channels.show', []);
    }

}