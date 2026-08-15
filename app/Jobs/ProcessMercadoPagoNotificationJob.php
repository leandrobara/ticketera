<?php

namespace App\Jobs;

use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Api\Notifications\MercadoPagoPaymentNotificationService;
use App\Services\Api\Dispatchers\MercadoPagoNotificationEventsDispatcherService;


class ProcessMercadoPagoNotificationJob implements ShouldQueue
{

    use Queueable;
    use Dispatchable;
    use SerializesModels;
    use InteractsWithQueue;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly string $paymentId,
    ) {
        //
    }

    public function handle(
        LockHelper $lockHelper,
        MercadoPagoNotificationEventsDispatcherService $dispatcher,
        MercadoPagoPaymentNotificationService $mercadoPagoPaymentNotificationService,
    ): void {
        $lockName = 'ProcessMercadoPagoNotificationJob:handle:paymentId:'.$this->paymentId;
        $lockIsGranted = $lockHelper->getLockByName($lockName, 30);

        if (!$lockIsGranted) {
            Log::info('Mercado Pago payment lock not granted, requeueing', [
                'lock_name' => $lockName,
                'payment_id' => $this->paymentId,
            ]);
            $dispatcher->dispatchProcessMercadoPagoNotificationJob($this->paymentId, 5);
            $this->delete();
            return;
        }

        try {
            $approvedOrderId = $mercadoPagoPaymentNotificationService->processPaymentNotification($this->paymentId);

            if (!$approvedOrderId) {
                return;
            }

            $dispatcher->dispatchSendOrderTicketsEmailJob($approvedOrderId);
        } finally {
            $lockHelper->releaseLockByName($lockName);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Mercado Pago notification job failed', [
            'payment_id' => $this->paymentId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
