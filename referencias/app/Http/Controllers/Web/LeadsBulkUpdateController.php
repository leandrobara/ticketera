<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class LeadsBulkUpdateController extends BaseController
{

    public function show(Request $request)
    {
        return view('web.leads-bulk-update.show', []);
    }

}
