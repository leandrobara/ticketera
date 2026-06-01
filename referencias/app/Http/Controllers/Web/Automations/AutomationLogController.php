<?php

namespace App\Http\Controllers\Web\Automations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class AutomationLogController extends BaseController
{

    public function list(Request $request)
    {
        return view('web.automations.automation-log.list', []);
    }
}