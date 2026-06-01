<?php

namespace App\Http\Controllers\API\Actions;


use App\Models\User;
use App\Helpers\SystemHelper;
use App\Services\API\UserService;
use App\Http\Resources\UserResource;
use App\Http\Requests\Actions\SyncWapiRequest;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\UnsyncWapiRequest;
use App\Http\Requests\Actions\SyncAWSEmailRequest;
use App\Http\Requests\Actions\UnsyncAWSEmailRequest;


class UserController extends BaseAPIController
{

    public function syncAWSEmail(SyncAWSEmailRequest $req)
    {
        $response = resolve(UserService::class)->syncEmailAddressToAWS(
            $req->input('email_from_address'), $req->input('email_from_name')
        );
        return $this->getSuccessResponse($response);
    }


    public function unsyncAWSEmail(UnsyncAWSEmailRequest $req)
    {
        $response = resolve(UserService::class)->unsyncUserEmailAddressFromAWS($req->user);
        return $this->getSuccessResponse($response);
    }


    public function syncWapi(SyncWapiRequest $req)
    {
        SystemHelper::setTimeLimit(120);
        $wapiSyncStatusDTO = resolve(UserService::class)->syncToWapi(
            $req->user, $req->input('wapi_session_phone_number')
        );
        return $this->getSuccessResponse($wapiSyncStatusDTO->toArray());
    }


    public function unsyncWapi(UnsyncWapiRequest $req)
    {
        SystemHelper::setTimeLimit(120);
        $response = resolve(UserService::class)->unsyncFromWapi($req->user);
        return $this->getSuccessResponse($response);
    }

}
