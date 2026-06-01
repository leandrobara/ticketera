<?php

namespace App\Http\Controllers\API;

use App\Models\Status;
use Illuminate\Http\Request;
use App\Services\API\StatusService;
use App\Http\Resources\StatusResource;
use App\Http\Requests\GetStatusRequest;
use App\Http\Requests\CreateStatusRequest;
use App\Http\Requests\DeleteStatusRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Requests\CountStatusLeadsRequest;
use App\Http\Resources\StatusResourceCollection;

class StatusController extends BaseAPIController
{

    public function list(Request $request)
    {
        $status = resolve(StatusService::class)->findAll();
        $rs = (new StatusResourceCollection($status))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function getOne(Status $status, GetStatusRequest $request)
    {
        return $this->getSuccessResponse((new StatusResource($status))->loadOptionsFromRequest($request));
    }


    public function leadsCount(Status $status, CountStatusLeadsRequest $request)
    {
        $leadsCount = resolve(StatusService::class)->getLeadsCount($status);
        return $this->getSuccessResponse($leadsCount);
    }


    public function create(CreateStatusRequest $request)
    {
        $status = resolve(StatusService::class)->create($request->validatedAttributes());
        return $this->getSuccessResponse((new StatusResource($status))->loadOptionsFromRequest($request));
    }


    public function update(Status $status, UpdateStatusRequest $request)
    {
        $status = resolve(StatusService::class)->update($status, $request->validatedAttributes());
        return $this->getSuccessResponse((new StatusResource($status))->loadOptionsFromRequest($request));
    }


    public function delete(Status $status, DeleteStatusRequest $request)
    {
        $status = resolve(StatusService::class)->delete($status);
        return $this->getSuccessResponse((new StatusResource($status))->loadOptionsFromRequest($request));
    }

}
