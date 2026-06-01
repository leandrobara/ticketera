<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class TasksController extends BaseController
{

    public function list(Request $request)
    {
        return view('web.tasks.list', []);
    }
}