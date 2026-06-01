<?php

namespace App\Http\Controllers\API;

use App\Models\UserCustomFilter;
use App\Services\API\UserCustomFilterService;
use App\Http\Resources\UserCustomFilter\UserCustomFilterResource;
use App\Http\Requests\UserCustomFilter\GetUserCustomFilterRequest;
use App\Http\Requests\UserCustomFilter\CreateUserCustomFilterRequest;
use App\Http\Requests\UserCustomFilter\UpdateUserCustomFilterRequest;
use App\Http\Requests\UserCustomFilter\DeleteUserCustomFilterRequest;
use App\Http\Resources\UserCustomFilter\UserCustomFilterCollectionResource;


class UserCustomFilterController extends BaseAPIController
{

    public function list()
    {
        $userCustomFilters = resolve(UserCustomFilterService::class)->findAllByUser();
        return $this->getSuccessResponse(
            new UserCustomFilterCollectionResource($userCustomFilters)
        );
    }


    public function getOne(UserCustomFilter $userCustomFilter, GetUserCustomFilterRequest $request)
    {
        return $this->getSuccessResponse(
            (new UserCustomFilterResource($userCustomFilter))->loadOptionsFromRequest($request)
        );
    }


    public function create(CreateUserCustomFilterRequest $request)
    {
        $userCustomFilter = resolve(UserCustomFilterService::class)->create($request->validatedDTO());
        return $this->getSuccessResponse(
            (new UserCustomFilterResource($userCustomFilter))->loadOptionsFromRequest($request)
        );
    }


    public function update(UserCustomFilter $userCustomFilter, UpdateUserCustomFilterRequest $request)
    {
        $user = resolve(UserCustomFilterService::class)->update($userCustomFilter, $request->validatedDTO());

        return $this->getSuccessResponse(
            (new UserCustomFilterResource($user))->loadOptionsFromRequest($request)
        );
    }


    public function delete(UserCustomFilter $userCustomFilter, DeleteUserCustomFilterRequest $request)
    {
        $user = resolve(UserCustomFilterService::class)->delete($userCustomFilter);

        return $this->getSuccessResponse(
            (new UserCustomFilterResource($user))->loadOptionsFromRequest($request)
        );
    }

}
