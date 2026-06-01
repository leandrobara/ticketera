<?php

namespace App\Http\Controllers\Web\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class DashboardController extends BaseController
{

    public function show(Request $request)
    {
        return view('web.reports.dashboard.page', []);
    }

}
