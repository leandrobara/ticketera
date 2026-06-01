<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Services\API\UserNotificationService;
use App\Http\Resources\UserNotificationResource;
use App\Http\Requests\CreateUserNotificationRequest;


class UserNotificationController extends BaseAPIController
{

    public function create(CreateUserNotificationRequest $request, User $user)
    {
        $response = resolve(UserNotificationService::class)->sendNotification($request->validateRequest());
        return $this->getSuccessResponse((new UserNotificationResource($response))->loadOptionsFromRequest($request));
    }

}
