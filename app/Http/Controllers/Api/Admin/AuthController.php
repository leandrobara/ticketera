<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Api\AuthService;
use App\Http\Resources\AuthResource;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Http\Controllers\Api\BaseAPIController;


class AuthController extends BaseAPIController
{

    public function login(AuthLoginRequest $req, AuthService $authService): JsonResponse
    {
        return response()->json(
            $this->getSuccessResponse(new AuthResource($authService->loginUser($req->validated())))
        );
    }


    public function me(Request $req): JsonResponse
    {
        return response()->json(
            $this->getSuccessResponse([
                'user' => new AuthUserResource($req->user()),
            ])
        );
    }


    public function logout(Request $req, AuthService $authService): JsonResponse
    {
        return response()->json(
            $this->getSuccessResponse(
                $authService->logoutUser($req->attributes->get('admin_access_token'))
            )
        );
    }

}
