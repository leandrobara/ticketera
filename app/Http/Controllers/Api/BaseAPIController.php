<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

class BaseAPIController extends Controller
{

    protected function getSuccessResponse(mixed $data): array
    {
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    protected function getErrorResponse(mixed $data): array
    {
        return [
            'success' => false,
            'data' => $data,
        ];
    }
}
