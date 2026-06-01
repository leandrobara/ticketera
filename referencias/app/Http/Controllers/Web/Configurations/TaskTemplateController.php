<?php

namespace App\Http\Controllers\Web\Configurations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;


class TaskTemplateController extends BaseController
{

    public function list(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.configurations.task-template.list', []);
    }

}
