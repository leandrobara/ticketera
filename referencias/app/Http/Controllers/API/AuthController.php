<?php

namespace App\Http\Controllers\API;

use App\Services\API\AuthService;
use App\Services\API\UserService;
use App\Http\Requests\AuthMeRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Http\Requests\GetUserRequest;
use App\Services\API\UserLoginService;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\AuthLogoutRequest;
use App\Http\Requests\AuthChangePasswordRequest;
use App\Http\Requests\AuthSendResetPasswordRequest;


class AuthController extends BaseAPIController
{

    public function login(AuthLoginRequest $request)
    {
        $params = $request->validatedAttributes();
        $authResponse = resolve(AuthService::class)->loginUser($params);
        $rs = new AuthResource($authResponse);

        $rememberTokenCookieName = config('auth.remember_token_cookie_name');
        $rememberToken = $authResponse['user']->remember_token;
        $tokenCookie = cookie($rememberTokenCookieName, $rememberToken, $params['minutes_to_expire']);

        $expirationDateStr = $params['expiration_date']->format('Y-m-d\TH:i:sP');
        $expirationDateCookieName = config('auth.expiration_date_cookie_name');
        $expirationDateCookie = cookie()->forever($expirationDateCookieName, $expirationDateStr);

        // log user
        resolve(UserLoginService::class)->registerLogin($authResponse, $request);
        return response($this->getSuccessResponse($rs))
            ->withCookie($tokenCookie)
            ->withCookie($expirationDateCookie)
        ;
    }


    public function logout(AuthLogoutRequest $request)
    {
        $auth = resolve(AuthService::class)->logoutUser($request->user);
        return response($this->getSuccessResponse($auth))
            ->withCookie(cookie(config('auth.remember_token_cookie_name'), '', -1))
            ->withCookie(cookie(config('auth.expiration_date_cookie_name'), '', -1))
        ;
    }


    public function me(AuthMeRequest $request)
    {
        return $this->getSuccessResponse((new UserResource($request->user))->loadOptionsFromRequest($request));
    }


    public function sendResetPasswordEmail(AuthSendResetPasswordRequest $request)
    {
        $user = resolve(AuthService::class)->sendResetPasswordEmail($request->getUserToResetPassword());
        return $this->getSuccessResponse($user);
    }


    public function doPasswordReset(AuthChangePasswordRequest $request)
    {
        $user = $request->getUserToChangePassword();
        $attributes = ['password' => $request->getNewPassword(), 'reset_password_token' => null];
        $response = resolve(UserService::class)->update($user, $attributes);
        return $this->getSuccessResponse($response);
    }

}
