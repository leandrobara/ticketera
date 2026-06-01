<?php

namespace App\Http\Controllers\Web\Configurations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class TagCategoryController extends BaseController
{

    public function list(Request $req)
    {
        saveVisitedScreenUrl($req);
        return view('web.configurations.tag-category.list', []);
    }
}