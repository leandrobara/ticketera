<?php

namespace App\Services\Api\Checkout;

use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderItem;
use App\Models\OrderItemPromotion;
use App\Models\Presentation;
use App\Models\PresentationTicketType;
use App\Models\Ticket;
use App\Services\Api\Admin\BuyerService;
use App\Services\Api\MercadoPagoService;
use App\Services\Api\OrderItemPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutOrderService
{
    public function __construct(
        private readonly BuyerService $buyerService,
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly OrderItemPricingService $orderItemPricingService,
    ) {
        //
    }

    public function create(array $data): array
    {
        $order = DB::transaction(function () use ($data) {
            $ticketType = PresentationTicketType::query()
                ->lockForUpdate()
                ->findOrFail($data['presentation_ticket_type_id'])
            ;

            $presentation = Presentation::query()
                ->with('season')
                ->lockForUpdate()
                ->findOrFail($ticketType->presentation_id)
            ;

            $this->validateAvailability($ticketType, $presentation->capacity, $data['quantity']);

            $buyer = $this->buyerService->create($data['buyer']);
            $promotion = $this->orderItemPricingService->resolvePromotion($ticketType, true, $data['promo_code'] ?? null);
            $pricing = $this->orderItemPricingService->calculateAmountsForTicketType(
                $ticketType,
                $data['quantity'],
                $promotion
            );

            $order = Order::create([
                'notes' => $data['notes'] ?? null,
                'source' => 'CHECKOUT',
                'status' => 'PENDING',
                'currency' => 'ARS',
                'buyer_id' => $buyer->id,
                'show_id' => $presentation->season->show_id,
                'code' => $this->makeUniqueCode('ORD', Order::class),
                'payment_method' => 'MERCADO_PAGO',
                'total_amount' => $pricing['total_amount'],
                'total_quantity' => $data['quantity'],
                'presentation_id' => $ticketType->presentation_id,
            ]);

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'name' => $ticketType->name,
                'show_id' => $presentation->season->show_id,
                'quantity' => $data['quantity'],
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

            return $order->fresh([
                'buyer',
                'presentation.season.show',
                'items',
            ]);
        });

        $preference = $this->mercadoPagoService->createPreference($order);

        $payment = Payment::create([
            'order_id' => $order->id,
            'currency' => $order->currency,
            'provider' => 'MERCADO_PAGO',
            'amount' => $order->total_amount,
            'show_id' => $order->show_id,
            'provider_status' => 'PENDING',
            'provider_preference_id' => $preference['id'] ?? null,
            'raw_response' => $preference,
        ]);

        return [
            'order' => $order->fresh([
                'buyer',
                'presentation.season.show',
                'items.promotionSnapshot',
                'payments',
            ]),
            'payment' => $payment,
            'preference' => [
                'id' => $preference['id'] ?? null,
                'init_point' => $preference['init_point'] ?? null,
                'sandbox_init_point' => $preference['sandbox_init_point'] ?? null,
            ],
        ];
    }

    private function validateAvailability(PresentationTicketType $ticketType, int $presentationCapacity, int $quantity): void
    {
        $assignedPresentationTickets = Ticket::query()
            ->where('presentation_id', $ticketType->presentation_id)
            ->whereIn('status', ['VALID', 'USED'])
            ->lockForUpdate()
            ->count()
        ;

        if (($assignedPresentationTickets + $quantity) > $presentationCapacity) {
            throw ValidationException::withMessages([
                'quantity' => ['presentation_capacity_exceeded'],
            ]);
        }

        if ($ticketType->stock === null) {
            return;
        }

        $assignedTicketTypeTickets = Ticket::query()
            ->where('presentation_ticket_type_id', $ticketType->id)
            ->whereIn('status', ['VALID', 'USED'])
            ->lockForUpdate()
            ->count()
        ;

        if (($assignedTicketTypeTickets + $quantity) > $ticketType->stock) {
            throw ValidationException::withMessages([
                'quantity' => ['presentation_ticket_type_stock_exceeded'],
            ]);
        }
    }

    private function makeUniqueCode(string $prefix, string $modelClass): string
    {
        do {
            $code = $prefix.'-'.Str::upper(Str::random(10));
        } while ($modelClass::query()->where('code', $code)->exists());

        return $code;
    }

}
