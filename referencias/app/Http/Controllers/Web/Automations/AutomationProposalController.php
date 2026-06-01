<?php

namespace App\Http\Controllers\Web\Automations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class AutomationProposalController extends BaseController
{

    public function list(Request $request)
    {
        return view('web.automations.automation-proposal.list', []);
    }
}