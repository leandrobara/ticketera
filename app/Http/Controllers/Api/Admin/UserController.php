<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\DeleteUserRequest;
use App\Http\Requests\Admin\GetUserRequest;
use App\Http\Requests\Admin\ListUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\Api\Admin\UserService;

class UserController extends BaseAPIController
{
    public function list(ListUserRequest $req): array
    {
        $users = resolve(UserService::class)->list($req->validated());
        return $this->getSuccessResponse($users);
    }

    public function create(CreateUserRequest $req): array
    {
        $user = resolve(UserService::class)->create($req->validated());
        return $this->getSuccessResponse($user);
    }

    public function show(User $user, GetUserRequest $req): array
    {
        $user = resolve(UserService::class)->getOne($user);
        return $this->getSuccessResponse($user);
    }

    public function update(User $user, UpdateUserRequest $req): array
    {
        $user = resolve(UserService::class)->update($user, $req->validated());
        return $this->getSuccessResponse($user);
    }

    public function delete(User $user, DeleteUserRequest $req): array
    {
        resolve(UserService::class)->delete($user);
        return $this->getSuccessResponse($user);
    }
}
