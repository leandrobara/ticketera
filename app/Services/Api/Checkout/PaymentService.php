<?php

namespace App\Services\Api\Checkout;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\PaymentRepository;

class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
    ) {
        //
    }

    public function createPendingMercadoPagoPayment(Order $order, array $preference): Payment
    {
        return $this->paymentRepository->store([
            'order_id' => $order->id,
            'provider' => 'MERCADO_PAGO',
            'show_id' => $order->show_id,
            'raw_response' => $preference,
            'currency' => $order->currency,
            'provider_status' => 'PENDING',
            'amount' => $order->total_amount,
            'provider_preference_id' => $preference['id'] ?? null,
        ]);
    }
}
