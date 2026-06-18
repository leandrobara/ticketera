<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreatePromotionRequest;
use App\Http\Requests\Admin\DeletePromotionRequest;
use App\Http\Requests\Admin\GetPromotionRequest;
use App\Http\Requests\Admin\ListPromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Models\Promotion;
use App\Services\Api\Admin\PromotionService;

class PromotionController extends BaseAPIController
{
    public function list(ListPromotionRequest $req): array
    {
        return $this->getSuccessResponse(
            resolve(PromotionService::class)->list($req->validated())
        );
    }

    public function create(CreatePromotionRequest $req): array
    {
        return $this->getSuccessResponse(
            resolve(PromotionService::class)->create($req->validated())
        );
    }

    public function show(Promotion $promotion, GetPromotionRequest $req): array
    {
        return $this->getSuccessResponse(
            resolve(PromotionService::class)->getOne($promotion)
        );
    }

    public function update(Promotion $promotion, UpdatePromotionRequest $req): array
    {
        return $this->getSuccessResponse(
            resolve(PromotionService::class)->update($promotion, $req->validated())
        );
    }

    public function delete(Promotion $promotion, DeletePromotionRequest $req): array
    {
        resolve(PromotionService::class)->delete($promotion);
        return $this->getSuccessResponse($promotion);
    }
}
