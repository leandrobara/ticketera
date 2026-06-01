<?php

namespace App\Http\Controllers\Web\Automations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class AutomationEmailSendController extends BaseController
{

    public function list(Request $request)
    {
        return view('web.automations.automation-email-send.list', []);
    }
}