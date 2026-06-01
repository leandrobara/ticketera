<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\AcquisitionChannel;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\AcquisitionChannelService;
use App\Http\Resources\AcquisitionChannelResource;
use App\Http\Requests\GetAcquisitionChannelRequest;
use App\Http\Requests\CreateAcquisitionChannelRequest;
use App\Http\Requests\DeleteAcquisitionChannelRequest;
use App\Http\Requests\UpdateAcquisitionChannelRequest;
use App\Http\Requests\CountAcquisitionChannelLeadsRequest;
use App\Http\Resources\AcquisitionChannelResourceCollection;


class AcquisitionChannelController extends BaseAPIController
{

    public function list(Request $request)
    {
        $channels = resolve(AcquisitionChannelService::class)->findAllByClient();
        $rs = (new AcquisitionChannelResourceCollection($channels))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function getOne(AcquisitionChannel $channel, GetAcquisitionChannelRequest $request)
    {
        return $this->getSuccessResponse((new AcquisitionChannelResource($channel))->loadOptionsFromRequest($request));
    }


    public function create(CreateAcquisitionChannelRequest $request)
    {
        $channel = resolve(AcquisitionChannelService::class)->create($request->validatedAttributes());
        return $this->getSuccessResponse((new AcquisitionChannelResource($channel))->loadOptionsFromRequest($request));
    }


    public function leadsCount(AcquisitionChannel $channel, CountAcquisitionChannelLeadsRequest $request)
    {
        $leadsCount = resolve(AcquisitionChannelService::class)->getLeadsCount($channel);
        return $this->getSuccessResponse($leadsCount);
    }


    public function update(AcquisitionChannel $channel, UpdateAcquisitionChannelRequest $request)
    {
        $channel = resolve(AcquisitionChannelService::class)->update($channel, $request->validatedAttributes());
        return $this->getSuccessResponse((new AcquisitionChannelResource($channel))->loadOptionsFromRequest($request));
    }


    public function delete(AcquisitionChannel $channel, DeleteAcquisitionChannelRequest $request)
    {
        $channel = resolve(AcquisitionChannelService::class)->delete($channel);
        return $this->getSuccessResponse((new AcquisitionChannelResource($channel))->loadOptionsFromRequest($request));
    }

}
