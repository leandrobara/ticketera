<?php

namespace App\Http\Controllers\Web\WAutomations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class WAutomationSequenceController extends BaseController
{

    public function showPage(Request $request)
    {
        return view('web.wautomations.sequence.page', []);
    }

}