<?php

namespace App\Http\Controllers\Web\Configurations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class AcquisitionChannelController extends BaseController
{

    public function list(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.configurations.acquisition-channel.list', []);
    }
}