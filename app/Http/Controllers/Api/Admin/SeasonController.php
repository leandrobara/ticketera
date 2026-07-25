<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateSeasonRequest;
use App\Http\Requests\Admin\DeleteSeasonRequest;
use App\Http\Requests\Admin\GetSeasonRequest;
use App\Http\Requests\Admin\ListSeasonRequest;
use App\Http\Requests\Admin\UpdateSeasonRequest;
use App\Models\Season;
use App\Services\Api\Admin\SeasonService;

class SeasonController extends BaseAPIController
{
    public function list(ListSeasonRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(SeasonService::class)->list($request->validated())
        );
    }

    public function create(CreateSeasonRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(SeasonService::class)->create($request->validated())
        );
    }

    public function show(Season $season, GetSeasonRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(SeasonService::class)->getOne($season)
        );
    }

    public function update(Season $season, UpdateSeasonRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(SeasonService::class)->update($season, $request->validated())
        );
    }

    public function delete(Season $season, DeleteSeasonRequest $request): array
    {
        resolve(SeasonService::class)->delete($season);

        return $this->getSuccessResponse($season);
    }
}
