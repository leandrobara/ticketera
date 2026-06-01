<?php

namespace App\Http\Controllers\Web\Configurations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class UserController extends BaseController
{

    public function emailSign(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.configurations.user.email-sign', []);
    }


    public function list(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.configurations.user.list', []);
    }

}