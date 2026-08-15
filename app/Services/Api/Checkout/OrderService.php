<?php

namespace App\Services\Api\Checkout;

use App\Models\Buyer;
use App\Models\Order;
use App\Models\Presentation;
use App\Repositories\OrderRepository;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {
        //
    }

    public function createPendingOrder(
        Buyer $buyer,
        Presentation $presentation,
        array $pricing,
        array $data,
    ): Order {

        $notes = $data['notes'] ?? null;
        $quantity = $data['quantity'] ?? 0;
        $attribution = $data['attribution'] ?? [];

        return $this->orderRepository->store([
            'notes' => $notes,
            'source' => 'CHECKOUT',
            'status' => 'PENDING',
            'currency' => 'ARS',
            'buyer_id' => $buyer->id,
            'total_quantity' => $quantity,
            'code' => $this->makeUniqueCode(),
            'payment_method' => 'MERCADO_PAGO',
            'fbp' => $attribution['fbp'] ?? null,
            'fbc' => $attribution['fbc'] ?? null,
            'presentation_id' => $presentation->id,
            'fbclid' => $attribution['fbclid'] ?? null,
            'total_amount' => $pricing['total_amount'],
            'show_id' => $presentation->season->show_id,
            'utm_term' => $attribution['utm_term'] ?? null,
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'utm_content' => $attribution['utm_content'] ?? null,
        ]);
    }

    private function makeUniqueCode(): string
    {
        do {
            $code = 'ORD-'.Str::upper(Str::random(10));
        } while (Order::query()->where('code', $code)->exists());

        return $code;
    }
}
