<?php

namespace App\Http\Controllers\API\Views;

use App\Models\BusinessArea;
use Illuminate\Http\Request;
use App\Services\API\BusinessAreaService;
use App\Http\Resources\BusinessAreaResource;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\ListBusinessAreaRequest;
use App\Http\Resources\BusinessAreaResourceCollection;
use App\Http\Requests\Views\ListClientBusinessAreaRequest;


class BusinessAreaController extends BaseAPIController
{

    public function list(ListBusinessAreaRequest $req)
    {
        $businessAreas = resolve(BusinessAreaService::class)->list($req->validated());
        return $this->getSuccessResponse(new BusinessAreaResourceCollection($businessAreas));
    }

}