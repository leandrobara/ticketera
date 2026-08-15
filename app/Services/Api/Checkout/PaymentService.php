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

    public function findLockedMercadoPagoPayment(Order $order): ?Payment
    {
        return $this->paymentRepository->findLockedMercadoPagoByOrderId($order->id);
    }

    public function saveFromMercadoPagoPayment(
        Order $order,
        ?Payment $payment,
        array $mercadoPagoPayment,
        string $paymentId,
        string $paymentStatus,
    ): Payment {
        $attrs = [
            'show_id' => $order->show_id,
            'currency' => $mercadoPagoPayment['currency_id'] ?? $order->currency,
            'amount' => $mercadoPagoPayment['transaction_amount'] ?? $order->total_amount,
            'provider_status' => $paymentStatus,
            'provider_payment_id' => (string) $paymentId,
            'raw_response' => $mercadoPagoPayment,
            'paid_at' => $paymentStatus === 'APPROVED'
                ? ($mercadoPagoPayment['date_approved'] ?? now())
                : $payment?->paid_at,
        ];

        if ($payment) {
            return $this->paymentRepository->update($payment, $attrs);
        }

        return $this->paymentRepository->store([
            'order_id' => $order->id,
            'provider' => 'MERCADO_PAGO',
            ...$attrs,
        ]);
    }
}
