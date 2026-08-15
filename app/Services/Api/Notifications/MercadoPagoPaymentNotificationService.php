<?php

namespace App\Services\Api\Notifications;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Api\MercadoPagoService;
use App\Services\Api\Checkout\OrderService;
use App\Services\Api\Checkout\PaymentService;
use App\Services\Api\Checkout\TicketService;
use App\Services\Api\Checkout\PresentationCapacityService;


class MercadoPagoPaymentNotificationService
{

    public function __construct(
        private readonly OrderService $orderService,
        private readonly TicketService $ticketService,
        private readonly PaymentService $paymentService,
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly PresentationCapacityService $presentationCapacityService,
    ) {
        //
    }


    public function processPaymentNotification(string $paymentId): ?int
    {
        $mercadoPagoPayment = $this->mercadoPagoService->getPayment($paymentId);

        return DB::transaction(function () use ($mercadoPagoPayment, $paymentId) {
            $order = $this->orderService->findAndLockFromMercadoPagoPayment($mercadoPagoPayment);

            if (!$order) {
                Log::warning('Mercado Pago notification order not found', [
                    'payment_id' => $paymentId,
                    'external_reference' => $mercadoPagoPayment['external_reference'] ?? null,
                    'metadata' => $mercadoPagoPayment['metadata'] ?? null,
                ]);
                return null;
            }

            $payment = $this->paymentService->findLockedMercadoPagoPayment($order);
            $paymentStatus = $this->normalizeMercadoPagoStatus($mercadoPagoPayment['status'] ?? null);

            if ($this->orderAlreadyCompleted($order, $payment, $paymentId, $paymentStatus)) {
                return $order->id;
            }

            $this->paymentService->saveFromMercadoPagoPayment(
                $order,
                $payment,
                $mercadoPagoPayment,
                $paymentId,
                $paymentStatus,
            );
            $this->orderService->updateStatusFromPayment(
                $order,
                $paymentStatus,
                $mercadoPagoPayment['date_approved'] ?? null,
            );

            if ($paymentStatus !== 'APPROVED') {
                return null;
            }

            $this->ticketService->createMissingTicketsForOrder($order);
            $this->presentationCapacityService->syncFromAssignedTickets($order->presentation_id);

            return $this->ticketService->orderHasAllTickets($order)
                ? $order->id
                : null
            ;
        });
    }


    private function orderAlreadyCompleted(
        Order $order,
        ?Payment $payment,
        string $paymentId,
        string $paymentStatus,
    ): bool {
        if (!$payment) {
            return false;
        }

        return $paymentStatus === 'APPROVED'
            && $payment->provider_payment_id === (string) $paymentId
            && $payment->provider_status === 'APPROVED'
            && $this->ticketService->orderHasAllTickets($order);
    }


    private function normalizeMercadoPagoStatus(?string $status): string
    {
        return match ($status) {
            'approved' => 'APPROVED',
            'pending' => 'PENDING',
            'in_process' => 'IN_PROCESS',
            'rejected' => 'REJECTED',
            'cancelled', 'canceled' => 'CANCELED',
            'refunded', 'charged_back' => 'REFUNDED',
            default => 'PENDING',
        };
    }
}
