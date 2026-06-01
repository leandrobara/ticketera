<?php

namespace App\Http\Controllers\API;

use Pusher\Pusher;
use App\Models\User;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Services\API\UserService;
use App\Models\GoogleAPIUserToken;
use App\Http\Resources\UserResource;
use App\Http\Requests\GetUserRequest;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\ListUserRequest;
use App\Services\API\WAPSenderService;
use App\Http\Requests\EnableUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\DisableUserRequest;
use App\Http\Requests\WapiSyncStatusRequest;
use App\Http\Resources\UserResourceCollection;
use App\Services\API\GoogleAPIUserTokenService;
use App\Http\Requests\UserWAPSenderSyncStatusRequest;
use App\Services\API\GoogleAPI\GoogleGmailAPIService;
use App\Services\API\GoogleAPI\GoogleCommonAPIService;
use App\Services\API\GoogleAPI\GooglePeopleAPIService;
use App\Http\Requests\GoogleAPI\CreateMyGoogleGmailAPITokenFromAuthCodeRequest;
use App\Http\Requests\GoogleAPI\CreateMyGooglePeopleAPITokenFromAuthCodeRequest;


class UserController extends BaseAPIController
{

    public function list(ListUserRequest $request)
    {
        $users = resolve(UserService::class)->findAll();
        $rs = (new UserResourceCollection($users))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function listEnabled(Request $request)
    {
        $users = resolve(UserService::class)->findAllEnabledByClient(request()->input('client'));
        $rs = (new UserResourceCollection($users))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function getOne(User $userToGet, GetUserRequest $request)
    {
        return $this->getSuccessResponse((new UserResource($userToGet))->loadOptionsFromRequest($request));
    }


    public function create(CreateUserRequest $request)
    {
        $user = resolve(UserService::class)->create($request->validatedAttributes(), $request->user);
        return $this->getSuccessResponse((new UserResource($user))->loadOptionsFromRequest($request));
    }


    public function update(User $userToUpdate, UpdateUserRequest $request)
    {
        $user = resolve(UserService::class)->update($userToUpdate, $request->validatedAttributes());
        return $this->getSuccessResponse((new UserResource($user))->loadOptionsFromRequest($request));
    }


    public function enable(User $userToEnable, EnableUserRequest $request)
    {
        $user = resolve(UserService::class)->enable($userToEnable, $request->user);
        return $this->getSuccessResponse((new UserResource($user))->loadOptionsFromRequest($request));
    }


    public function disable(User $userToDisable, DisableUserRequest $request)
    {
        $user = resolve(UserService::class)->disable($userToDisable, $request->user);
        return $this->getSuccessResponse((new UserResource($user))->loadOptionsFromRequest($request));
    }


    public function getMyEmailSign()
    {
        $emailSign = resolve(UserService::class)->getMyEmailSign();
        return $this->getSuccessResponse($emailSign);
    }


    public function getMyGooglePeopleAPIAuthURL(Request $req)
    {
        $authUrl = resolve(GooglePeopleAPIService::class)->getGoogleAuthUrl($req->user);
        return $this->getSuccessResponse($authUrl);
    }


    public function isMyGooglePeopleAPITokenEnabled(Request $req)
    {
        $enabled = resolve(GooglePeopleAPIService::class)->isAPIEnabled($req->user->googlePeopleAPIUserToken);
        return $this->getSuccessResponse($enabled);
    }


    public function unsyncMyGooglePeopleAPIToken(Request $req)
    {
        $deletedToken = resolve(GoogleAPIUserTokenService::class)->deleteByUserAndType(
            $req->user, GoogleAPIUserToken::PEOPLE_API_TYPE
        );
        return $this->getSuccessResponse($deletedToken);
    }


    public function createMyGooglePeopleAPITokenFromAuthCode(CreateMyGooglePeopleAPITokenFromAuthCodeRequest $req)
    {
        resolve(GooglePeopleAPIService::class)->getAndStoreAccessTokenFromAuthCode($req->user, $req->getAuthCode());
        return $this->getSuccessResponse(true);
    }


    public function getMyGoogleGmailAPIAuthURL(Request $req)
    {
        $authUrl = resolve(GoogleGmailAPIService::class)->getGoogleAuthUrl($req->user);
        return $this->getSuccessResponse($authUrl);
    }


    public function isMyGoogleGmailAPITokenEnabled(Request $req)
    {
        $enabled = resolve(GoogleGmailAPIService::class)->isAPIEnabled($req->user->googleGmailAPIUserToken);
        return $this->getSuccessResponse($enabled);
    }


    public function unsyncMyGoogleGmailAPIToken(Request $req)
    {
        $deletedToken = resolve(GoogleAPIUserTokenService::class)->deleteByUserAndType(
            $req->user, GoogleAPIUserToken::GMAIL_API_TYPE
        );
        return $this->getSuccessResponse($deletedToken);
    }


    public function createMyGoogleGmailAPITokenFromAuthCode(CreateMyGoogleGmailAPITokenFromAuthCodeRequest $req)
    {
        resolve(GoogleGmailAPIService::class)->getAndStoreAccessTokenFromAuthCode($req->user, $req->getAuthCode());
        return $this->getSuccessResponse(true);
    }


    public function getMyEmailAWSSyncStatus(Request $request)
    {
        $emailSynced = resolve(UserService::class)->isAWSEmailSynced();
        return $this->getSuccessResponse([
            'synced' => $emailSynced,
            'email_from_name' => $request->user->email_from_name,
            'email_from_address' => $request->user->email_from_address,
        ]);
    }


    public function getMyWapiSyncStatus(WapiSyncStatusRequest $req)
    {
        SystemHelper::setTimeLimit(240);
        $wapiSyncStatusDTO = resolve(UserService::class)->wapiSyncStatus($req->user);
        return $this->getSuccessResponse($wapiSyncStatusDTO->toArray());
    }


    public function getUserWAPSenderSyncStatus(User $userToCheck, UserWAPSenderSyncStatusRequest $req)
    {
        resolve(WAPSenderService::class)->triggerWAPSyncStatusPusherEvent($userToCheck);
        $syncResponsesData = resolve(WAPSenderService::class)->getWAPSyncStatusResponsesData($userToCheck);
        return $this->getSuccessResponse([
            'syncResponsesData' => $syncResponsesData,
            'userIsEnabled' => $userToCheck->wap_sender_session_phone_number ? true : false,
            'clientIsEnabled' => $req->client->clientSettings->enable_whatsapp_sender_job_sending,
        ]);
    }


    public function getMyWAPSenderSyncStatus(Request $req)
    {
        resolve(WAPSenderService::class)->triggerWAPSyncStatusPusherEvent($req->user);
        $syncResponsesData = resolve(WAPSenderService::class)->getWAPSyncStatusResponsesData($req->user);
        return $this->getSuccessResponse([
            'syncResponsesData' => $syncResponsesData,
            'userIsEnabled' => $req->user->wap_sender_session_phone_number ? true : false,
            'clientIsEnabled' => $req->client->clientSettings->enable_whatsapp_sender_job_sending,
        ]);
    }


    private function waitForBrowserConfirmation(string $redisKey): bool
    {
        $start = time();
        $timeoutSeconds = 5;
        while (time() - $start < $timeoutSeconds) {
            $hasResponse = Cache::store('redis')->has($redisKey);
            if ($hasResponse) {
                return true;
            }
            usleep(150000);
        }
        return false;
    }

}
