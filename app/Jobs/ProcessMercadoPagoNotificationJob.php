<?php

namespace App\Jobs;

use App\Services\Api\Notifications\MercadoPagoNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMercadoPagoNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly string $paymentId,
    ) {
        //
    }

    public function handle(MercadoPagoNotificationService $mercadoPagoNotificationService): void
    {
        $approvedOrderId = $mercadoPagoNotificationService->handlePaymentNotification($this->paymentId);

        if (!$approvedOrderId) {
            return;
        }

        SendOrderTicketsEmailJob::dispatch($approvedOrderId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Mercado Pago notification job failed', [
            'payment_id' => $this->paymentId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
