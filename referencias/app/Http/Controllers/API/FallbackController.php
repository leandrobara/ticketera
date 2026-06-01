<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseAPIController;


class FallbackController extends BaseAPIController
{

    public function index(Request $req)
    {
        return response()->json(['success' => false, 'message' => 'Page Not Found'], 404);
    }

}
