<?php

namespace App\Http\Controllers\Web\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class UTMTraceReportController extends BaseController
{

    // Deprecado, no se usa.
    public function show(Request $request)
    {
        return view('web.reports.utm-trace.show', []);
    }

}