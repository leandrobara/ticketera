<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\StatusCategory;
use App\Services\API\StatusCategoryService;
use App\Http\Resources\StatusCategoryResource;
use App\Http\Requests\GetStatusCategoryRequest;
use App\Http\Requests\OrderUpStatusCategoryRequest;
use App\Http\Requests\CreateStatusCategoryRequest;
use App\Http\Requests\DeleteStatusCategoryRequest;
use App\Http\Requests\UpdateStatusCategoryRequest;
use App\Http\Requests\OrderDownStatusCategoryRequest;
use App\Http\Resources\StatusCategoryResourceCollection;
use App\Http\Requests\CountStatusCategoryStatusRelatedRequest;


class StatusCategoryController extends BaseAPIController
{

    public function list(Request $req)
    {
        $statusCategory = resolve(StatusCategoryService::class)->findAllByClient();
        $rs = (new StatusCategoryResourceCollection($statusCategory))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function create(CreateStatusCategoryRequest $req)
    {
        $statusCategory = resolve(StatusCategoryService::class)->create($req->validated());
        return $this->getSuccessResponse((new StatusCategoryResource($statusCategory))->loadOptionsFromRequest($req));
    }


    public function update(StatusCategory $statusCategory, UpdateStatusCategoryRequest $req)
    {
        $statusCategory = resolve(StatusCategoryService::class)->update($statusCategory, $req->validated());
        return $this->getSuccessResponse((new StatusCategoryResource($statusCategory))->loadOptionsFromRequest($req));
    }


    public function delete(StatusCategory $statusCategory, DeleteStatusCategoryRequest $req)
    {
        $statusCategory = resolve(StatusCategoryService::class)->delete($statusCategory);
        return $this->getSuccessResponse((new StatusCategoryResource($statusCategory))->loadOptionsFromRequest($req));
    }


    public function getOne(StatusCategory $statusCategory, GetStatusCategoryRequest $req)
    {
        return $this->getSuccessResponse((new StatusCategoryResource($statusCategory))->loadOptionsFromRequest($req));
    }


    public function orderUp(StatusCategory $statusCategory, OrderUpStatusCategoryRequest $req)
    {
        $direction = 'up';
        $statusCategory = resolve(StatusCategoryService::class)->changeOrder($statusCategory, $direction);
        return $this->getSuccessResponse((new StatusCategoryResource($statusCategory))->loadOptionsFromRequest($req));
    }


    public function orderDown(StatusCategory $statusCategory, OrderDownStatusCategoryRequest $req)
    {
        $direction = 'down';
        $statusCategory = resolve(StatusCategoryService::class)->changeOrder($statusCategory, $direction);
        return $this->getSuccessResponse((new StatusCategoryResource($statusCategory))->loadOptionsFromRequest($req));
    }


    public function statusRelatedCount(StatusCategory $statusCategory, CountStatusCategoryStatusRelatedRequest $req)
    {
        $statusRelatedCount = resolve(StatusCategoryService::class)->getStatusRelatedCount($statusCategory);
        return $this->getSuccessResponse(['statusRelatedCount' => $statusRelatedCount]);
    }

}
