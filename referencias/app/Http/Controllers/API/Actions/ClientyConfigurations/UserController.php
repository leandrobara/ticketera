<?php

namespace App\Http\Controllers\API\Actions\ClientyConfigurations;

use App\Models\User;
use App\Models\Client;
use App\Services\API\UserService;
use App\Models\GoogleAPIUserToken;
use App\Http\Resources\UserResource;
use App\Services\API\GoogleAPIUserTokenService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\ClientyConfigurations\ResetUserLoginSessionRequest;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateWapSenderRetryDelayDays;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateUserGoogleGmailAppNameRequest;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateUserWapSenderSessionPhoneNumber;


class UserController extends BaseAPIController
{

    public function resetUserLoginSession(Client $requestedClient, ResetUserLoginSessionRequest $req)
    {
        $users = $req->getUsers();
        resolve(UserService::class)->resetLoginSessions($users);
        return $this->getSuccessResponse($req->validated());
    }


    public function updateUserGoogleGmailAppName(User $requestedUser, UpdateUserGoogleGmailAppNameRequest $req)
    {
        // Borro el token (si existe), por que lo cambio de App, no le sirve más.
        $deletedGmailToken = resolve(GoogleAPIUserTokenService::class)->deleteByUserAndType(
            $requestedUser, GoogleAPIUserToken::GMAIL_API_TYPE
        );
        $user = resolve(UserService::class)->update(
            $requestedUser, ['google_gmail_app_name' => $req->input('googleGmailAppName')]
        );
        return $this->getSuccessResponse((new UserResource($user))->loadOptionsFromRequest($req));
    }


    public function updateWapSenderSessionPhoneNumber(
        Client $requestedClient,
        User $requestedUser,
        UpdateUserWapSenderSessionPhoneNumber $req
    ) {
        $user = resolve(UserService::class)->update(
            $requestedUser, ['wap_sender_session_phone_number' => $req->input('wapSenderSessionPhoneNumber')]
        );
        return $this->getSuccessResponse((new UserResource($user))->loadOptionsFromRequest($req));
    }


    public function updateWapSenderRetryDelayDays(
        Client $requestedClient,
        User $requestedUser,
        UpdateWapSenderRetryDelayDays $req
    ) {
        $user = resolve(UserService::class)->update(
            $requestedUser, ['wap_sender_retry_delay_days' => $req->input('wapSenderRetryDelayDays')]
        );
        return $this->getSuccessResponse((new UserResource($user))->loadOptionsFromRequest($req));
    }

}
