<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Api\AuthService;
use App\Http\Resources\AuthResource;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Http\Controllers\Api\BaseAPIController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;


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

    public function update(Request $req): JsonResponse
    {
        $user = $req->user();
        $data = $req->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'string'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        if (trim($data['name']) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'Ingresá un nombre.',
            ]);
        }

        $user->name = trim($data['name']);
        $user->email = $data['email'];
        if (! empty($data['password'])) $user->password = $data['password'];
        $user->save();

        return response()->json($this->getSuccessResponse([
            'user' => new AuthUserResource($user),
        ]));
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
