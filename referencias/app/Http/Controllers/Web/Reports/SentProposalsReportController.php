<?php

namespace App\Http\Controllers\Web\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class SentProposalsReportController extends BaseController
{

    public function list(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.reports.sent-proposals.list', []);
    }

}