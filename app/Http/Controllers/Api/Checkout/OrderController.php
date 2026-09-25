<?php

namespace App\Http\Controllers\Api\Checkout;

use App\Http\Controllers\Api\BaseAPIController;
use App\Services\Api\Checkout\CheckoutOrderService;
use App\Http\Requests\Checkout\CreateCheckoutOrderRequest;

class OrderController extends BaseAPIController
{
    public function create(CreateCheckoutOrderRequest $req): array
    {
        $initPoint = resolve(CheckoutOrderService::class)->create($req->validated());

        return $this->getSuccessResponse([
            'preference' => [
                'init_point' => $initPoint,
            ],
        ]);
    }
}
