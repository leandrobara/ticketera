<?php

namespace App\Http\Controllers\Web\Automations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class AutomationFlowChartController extends BaseController
{
    public function showFlowChartPage(Request $request)
    {
        return view('web.automations.automation-flow-chart.page', []);
    }

}
