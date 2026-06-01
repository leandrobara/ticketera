<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class TestController extends BaseController
{

    public function index(Request $request)
    {
        return view('web.home.index', []);
    }
}
