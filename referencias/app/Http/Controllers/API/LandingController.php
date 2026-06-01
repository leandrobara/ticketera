<?php

namespace App\Http\Controllers\API;

use App\Models\Landing;
use Illuminate\Http\Request;
use App\Services\API\LandingService;
use App\Http\Resources\LandingResource;
use App\Http\Requests\GetLandingRequest;
use App\Http\Requests\UpdateLandingRequest;
use App\Http\Requests\DeleteLandingRequest;
use App\Http\Requests\CreateLandingRequest;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\LandingResourceCollection;

class LandingController extends BaseAPIController
{

    public function list(Request $request)
    {
        $landings = resolve(LandingService::class)->findAllByClient();
        $rs = (new LandingResourceCollection($landings))->loadOptionsFromRequest($request);

        return $this->getSuccessResponse($rs);
    }

    public function getOne(Landing $landing, GetLandingRequest $request)
    {
        return $this->getSuccessResponse((new LandingResource($landing))->loadOptionsFromRequest($request));
    }

    public function create(CreateLandingRequest $request)
    {
        $landing = resolve(LandingService::class)->create($request->validatedAttributes());

        return $this->getSuccessResponse((new LandingResource($landing))->loadOptionsFromRequest($request));
    }

    public function update(Landing $landing, UpdateLandingRequest $request)
    {
        $landing = resolve(LandingService::class)->update($landing, $request->validatedAttributes());

        return $this->getSuccessResponse((new LandingResource($landing))->loadOptionsFromRequest($request));
    }

    public function delete(Landing $landing, DeleteLandingRequest $request)
    {
        $landing = resolve(LandingService::class)->delete($landing);

        return $this->getSuccessResponse((new LandingResource($landing))->loadOptionsFromRequest($request));
    }
}
