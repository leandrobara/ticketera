<?php

namespace App\Http\Controllers\Web\Configurations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class SyncGoogleGmailController extends BaseController
{

    public function show(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.configurations.sync-google-gmail.show', []);
    }
}