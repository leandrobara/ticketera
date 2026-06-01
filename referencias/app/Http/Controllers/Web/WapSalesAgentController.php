<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class WapSalesAgentController extends BaseController
{

    public function internalTest(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.wap-sales-agent.internal-test', []);
    }

}
