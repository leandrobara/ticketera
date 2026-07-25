<?php

namespace App\Http\Controllers\Api\Checkout;

use App\Http\Controllers\Api\BaseAPIController;
use App\Services\Api\Checkout\CheckoutOrderService;
use App\Http\Requests\Checkout\CreateCheckoutOrderRequest;


class OrderController extends BaseAPIController
{
    public function create(CreateCheckoutOrderRequest $req): array
    {
        return $this->getSuccessResponse(resolve(CheckoutOrderService::class)->create($req->validated()));
    }
}
