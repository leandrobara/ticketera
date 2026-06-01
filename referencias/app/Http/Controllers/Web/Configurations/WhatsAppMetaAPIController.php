<?php

namespace App\Http\Controllers\Web\Configurations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class WhatsAppMetaAPIController extends BaseController
{

    public function show(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.configurations.whatsapp-meta-api.show', []);
    }

}