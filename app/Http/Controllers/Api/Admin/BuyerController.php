<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Buyer;
use App\Services\Api\Admin\BuyerService;
use App\Http\Requests\Admin\GetBuyerRequest;
use App\Http\Requests\Admin\ListBuyerRequest;
use App\Http\Requests\Admin\UpdateBuyerRequest;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateBuyerRequest;
use App\Http\Requests\Admin\DeleteBuyerRequest;


class BuyerController extends BaseAPIController
{
    public function list(ListBuyerRequest $req): array
    {
        $buyerList = resolve(BuyerService::class)->list($req->validated());
        return $this->getSuccessResponse($buyerList);
    }

    public function create(CreateBuyerRequest $req): array
    {
        $buyer = resolve(BuyerService::class)->create($req->validated());
        return $this->getSuccessResponse($buyer);
    }

    public function show(Buyer $buyer, GetBuyerRequest $req): array
    {
        $buyer = resolve(BuyerService::class)->getOne($buyer);
        return $this->getSuccessResponse($buyer);
    }

    public function update(Buyer $buyer, UpdateBuyerRequest $req): array
    {
        $buyer = resolve(BuyerService::class)->update($buyer, $req->validated());
        return $this->getSuccessResponse($buyer);
    }

    public function delete(Buyer $buyer, DeleteBuyerRequest $req): array
    {
        resolve(BuyerService::class)->delete($buyer);
        return $this->getSuccessResponse($buyer);
    }
}
