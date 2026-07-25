<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\OrderItemPricingService;
use App\Http\Requests\OrderItemPricing\CalculateAmountsRequest;

class OrderItemPricingController extends BaseAPIController
{
    public function calculateAmounts(CalculateAmountsRequest $req): array
    {
        return $this->getSuccessResponse(
            resolve(OrderItemPricingService::class)->calculateAmounts($req->validated())
        );
    }
}
