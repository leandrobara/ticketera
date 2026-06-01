<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controller;


class BaseAPIController extends Controller
{
 
    public function getSuccessResponse($data)
    {
        return [
            'success' => true,
            'data' => $data,
        ];
    }

 
    public function getErrorResponse($data)
    {
        return [
            'success' => false,
            'data' => $data,
        ];
    }

}
