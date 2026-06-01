<?php

namespace App\Http\Controllers\Web\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class SentEmailsController extends BaseController
{

    public function list(Request $request)
    {
        return view('web.reports.sent-emails.list', []);
    }

    public function listMassive(Request $request)
    {
        return view('web.reports.sent-massive-emails.list', []);
    }
}
