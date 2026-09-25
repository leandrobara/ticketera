<?php

namespace App\Repositories;

use App\Models\OrderItemPromotion;

class OrderItemPromotionRepository
{
    public function store(array $attrs): OrderItemPromotion
    {
        return OrderItemPromotion::create($attrs);
    }
}
