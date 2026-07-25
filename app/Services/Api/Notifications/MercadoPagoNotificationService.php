<?php

namespace App\Services\Api\Notifications;

use App\Jobs\SendOrderTicketsEmailJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\Ticket;
use App\Services\Api\Admin\OrderService;
use App\Services\Api\MercadoPagoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MercadoPagoNotificationService
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPagoService,
    ) {
        //
    }

    public function handleNotification(array $_payload, ?string $paymentId, ?string $notificationType): void
    {
        if (blank($paymentId)) {
            return;
        }

        if ($notificationType === 'payment') {
            $this->processPayment($this->mercadoPagoService->getPayment($paymentId), $paymentId);
            return;
        }

        if ($notificationType === 'merchant_order') {
            $this->processMerchantOrder($paymentId);
            return;
        }
    }

    private function processMerchantOrder(string $merchantOrderId): void
    {
        $merchantOrder = $this->mercadoPagoService->getMerchantOrder($merchantOrderId);
        $payments = $merchantOrder['payments'] ?? [];

        if (count($payments) === 0) {
            return;
        }

        foreach ($payments as $payment) {
            if (blank($payment['id'] ?? null)) {
                continue;
            }

            $paymentId = (string) $payment['id'];
            $this->processPayment($this->mercadoPagoService->getPayment($paymentId), $paymentId);
        }
    }

    private function processPayment(array $mercadoPagoPayment, string $paymentId): void
    {
        $this->withPaymentLock($paymentId, function () use ($mercadoPagoPayment, $paymentId) {
            $this->processPaymentWithLock($mercadoPagoPayment, $paymentId);
        });
    }

    private function processPaymentWithLock(array $mercadoPagoPayment, string $paymentId): void
    {
        DB::transaction(function () use ($mercadoPagoPayment, $paymentId) {
            $order = $this->findOrder($mercadoPagoPayment);

            if (!$order) {
                Log::warning('Mercado Pago notification order not found', [
                    'payment_id' => $paymentId,
                    'external_reference' => $mercadoPagoPayment['external_reference'] ?? null,
                    'metadata' => $mercadoPagoPayment['metadata'] ?? null,
                ]);
                return;
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
                $this->dispatchOrderTicketsEmail($order);
                return;
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

            if ($paymentStatus === 'APPROVED') {
                $this->createTicketsIfNeeded($order);
                resolve(OrderService::class)->syncPresentationStatusFromCapacity(
                    Presentation::query()
                        ->lockForUpdate()
                        ->findOrFail($order->presentation_id)
                );
                $this->dispatchOrderTicketsEmail($order);
            }
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

    private function withPaymentLock(string $paymentId, callable $callback): void
    {
        $lockName = "mercado_pago_payment_{$paymentId}";
        $result = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$lockName]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            Log::warning('Mercado Pago payment lock not acquired', [
                'payment_id' => $paymentId,
                'lock_name' => $lockName,
            ]);
            return;
        }

        try {
            $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) as released', [$lockName]);
        }
    }

    private function dispatchOrderTicketsEmail(Order $order): void
    {
        if ($order->tickets_email_sent_at) {
            return;
        }

        SendOrderTicketsEmailJob::dispatch($order->id)->afterCommit();
    }

    private function makeUniqueCode(string $prefix, string $modelClass): string
    {
        do {
            $code = $prefix.'-'.Str::upper(Str::random(10));
        } while ($modelClass::query()->where('code', $code)->exists());

        return $code;
    }
}
