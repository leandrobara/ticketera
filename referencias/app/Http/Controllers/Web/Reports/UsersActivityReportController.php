<?php

namespace App\Http\Controllers\Web\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class UsersActivityReportController extends BaseController
{

    public function list(Request $request)
    {
        return view('web.reports.users-activity.list', []);
    }

}