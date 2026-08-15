<?php

namespace App\Services\Api\Notifications;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Support\Str;
use App\Models\Presentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Api\Admin\OrderService;
use App\Services\Api\MercadoPagoService;


class MercadoPagoNotificationService
{

    public function __construct(
        private readonly MercadoPagoService $mercadoPagoService,
    ) {
        //
    }

    public function handlePaymentNotification(string $paymentId): ?int
    {
        $mercadoPagoPayment = $this->mercadoPagoService->getPayment($paymentId);

        return $this->processPayment($mercadoPagoPayment, $paymentId);
    }

    private function processPayment(array $mercadoPagoPayment, string $paymentId): ?int
    {
        return $this->withPaymentLock($paymentId, function () use ($mercadoPagoPayment, $paymentId) {
            return $this->processPaymentWithLock($mercadoPagoPayment, $paymentId);
        });
    }

    private function processPaymentWithLock(array $mercadoPagoPayment, string $paymentId): ?int
    {
        return DB::transaction(function () use ($mercadoPagoPayment, $paymentId) {
            $order = $this->findOrder($mercadoPagoPayment);

            if (!$order) {
                Log::warning('Mercado Pago notification order not found', [
                    'payment_id' => $paymentId,
                    'external_reference' => $mercadoPagoPayment['external_reference'] ?? null,
                    'metadata' => $mercadoPagoPayment['metadata'] ?? null,
                ]);
                return null;
            }

            $order = Order::query()
                ->with(['items', 'tickets'])
                ->lockForUpdate()
                ->findOrFail($order->id)
            ;

            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('provider', 'MERCADO_PAGO')
                ->lockForUpdate()
                ->first()
            ;

            if (!$payment) {
                $payment = new Payment([
                    'order_id' => $order->id,
                    'provider' => 'MERCADO_PAGO',
                    'show_id' => $order->show_id,
                    'currency' => $order->currency,
                    'amount' => $order->total_amount,
                ]);
            }

            $paymentStatus = $this->normalizePaymentStatus($mercadoPagoPayment['status'] ?? null);

            if (
                $paymentStatus === 'APPROVED'
                && $payment->provider_payment_id === (string) $paymentId
                && $payment->provider_status === 'APPROVED'
                && $order->tickets()->count() >= $order->total_quantity
            ) {
                return $order->id;
            }

            $payment->fill([
                'show_id' => $order->show_id,
                'currency' => $mercadoPagoPayment['currency_id'] ?? $order->currency,
                'amount' => $mercadoPagoPayment['transaction_amount'] ?? $order->total_amount,
                'provider_status' => $paymentStatus,
                'provider_payment_id' => (string) $paymentId,
                'raw_response' => $mercadoPagoPayment,
                'paid_at' => $paymentStatus === 'APPROVED'
                    ? ($mercadoPagoPayment['date_approved'] ?? now())
                    : $payment->paid_at,
            ])->save();

            $orderStatus = $this->mapOrderStatus($paymentStatus);

            $order->update([
                'status' => $orderStatus,
                'approved_at' => $paymentStatus === 'APPROVED'
                    ? ($mercadoPagoPayment['date_approved'] ?? now())
                    : $order->approved_at,
            ]);

            if ($paymentStatus !== 'APPROVED') {
                return null;
            }

            $this->createTicketsIfNeeded($order);
            resolve(OrderService::class)->syncPresentationStatusFromCapacity(
                Presentation::query()
                    ->lockForUpdate()
                    ->findOrFail($order->presentation_id)
            );

            return $order->tickets()->count() >= $order->total_quantity
                ? $order->id
                : null;
        });
    }

    private function findOrder(array $mercadoPagoPayment): ?Order
    {
        $orderId = $mercadoPagoPayment['metadata']['order_id'] ?? null;

        if ($orderId) {
            $order = Order::query()->find($orderId);

            if ($order) {
                return $order;
            }
        }

        $orderCode = $mercadoPagoPayment['external_reference']
            ?? $mercadoPagoPayment['metadata']['order_code']
            ?? null;

        if (!$orderCode) {
            return null;
        }

        return Order::query()
            ->where('code', $orderCode)
            ->first();
    }

    private function normalizePaymentStatus(?string $status): string
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

    private function mapOrderStatus(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'APPROVED' => 'APPROVED',
            'IN_PROCESS' => 'IN_PROCESS',
            'REJECTED' => 'REJECTED',
            'CANCELED' => 'CANCELED',
            'REFUNDED' => 'REFUNDED',
            default => 'PENDING',
        };
    }

    private function createTicketsIfNeeded(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $existingTicketsCount = Ticket::query()
                ->where('order_item_id', $item->id)
                ->lockForUpdate()
                ->count()
            ;
            $missingTicketsCount = max(0, $item->quantity - $existingTicketsCount);

            if ($missingTicketsCount === 0) {
                continue;
            }

            for ($i = 0; $i < $missingTicketsCount; $i++) {
                Ticket::create([
                    'status' => 'VALID',
                    'order_id' => $order->id,
                    'show_id' => $order->show_id,
                    'order_item_id' => $item->id,
                    'code' => $this->makeUniqueCode('TCK', Ticket::class),
                    'presentation_id' => $order->presentation_id,
                    'presentation_ticket_type_id' => $item->presentation_ticket_type_id,
                ]);
            }
        }
    }

    private function withPaymentLock(string $paymentId, callable $callback): ?int
    {
        $lockName = "mercado_pago_payment_{$paymentId}";
        $result = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$lockName]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            Log::warning('Mercado Pago payment lock not acquired', [
                'payment_id' => $paymentId,
                'lock_name' => $lockName,
            ]);
            return null;
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) as released', [$lockName]);
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
