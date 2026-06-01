<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class LoginController extends BaseController
{

    public function login(Request $request)
    {
        return view('web.login.index', []);
    }
}
