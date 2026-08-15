<?php

namespace App\Services\Api\Checkout;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemPromotion;
use App\Models\Presentation;
use App\Models\PresentationTicketType;
use App\Repositories\OrderItemRepository;
use App\Services\Api\OrderItemPricingService;

class OrderItemService
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository,
        private readonly OrderItemPricingService $orderItemPricingService,
    ) {
        //
    }

    public function createItem(
        Order $order,
        Presentation $presentation,
        PresentationTicketType $ticketType,
        array $pricing,
        int $quantity,
        ?PresentationTicketType $promotion,
    ): OrderItem {
        $orderItem = $this->orderItemRepository->store([
            'order_id' => $order->id,
            'name' => $ticketType->name,
            'show_id' => $presentation->season->show_id,
            'quantity' => $quantity,
            'paid_quantity' => $pricing['paid_quantity'],
            'unit_price' => $pricing['unit_price'],
            'unit_service_fee' => $pricing['unit_service_fee'],
            'service_fee_type' => $pricing['service_fee_type'],
            'service_fee_fixed_amount' => $pricing['service_fee_fixed_amount'],
            'service_fee_percentage' => $pricing['service_fee_percentage'],
            'service_fee_base_amount' => $pricing['service_fee_base_amount'],
            'service_fee_minimum_applied' => $pricing['service_fee_minimum_applied'],
            'service_fee_minimum_unit_amount' => $pricing['service_fee_minimum_unit_amount'],
            'subtotal_amount' => $pricing['subtotal_amount'],
            'discount_amount' => $pricing['discount_amount'],
            'service_fee_total_amount' => $pricing['service_fee_total_amount'],
            'total_amount' => $pricing['total_amount'],
            'presentation_ticket_type_id' => $ticketType->id,
        ]);

        if ($promotion) {
            OrderItemPromotion::create([
                'order_item_id' => $orderItem->id,
                'promotion_name' => $this->orderItemPricingService->getPromotionName($promotion),
                'promotion_type' => $promotion->promotion_type,
                'promotion_value' => $promotion->promotion_value,
                'promotion_access_code' => $promotion->promotion_access_code,
                'bundle_quantity' => $promotion->promotion_bundle_quantity,
                'pay_quantity' => $promotion->promotion_pay_quantity,
                'discount_amount' => $pricing['discount_amount'],
            ]);
        }

        return $orderItem;
    }
}
