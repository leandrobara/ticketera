<?php

namespace App\Services\Api\Notifications;

use Illuminate\Support\Facades\Log;
use App\Http\Requests\Notifications\MercadoPagoNotificationRequest;
use App\Services\Api\Dispatchers\MercadoPagoNotificationEventsDispatcherService;


class MercadoPagoNotificationService
{
    public function __construct(
        private readonly MercadoPagoNotificationEventsDispatcherService $dispatcher,
    ) {
        //
    }

    public function handle(MercadoPagoNotificationRequest $req): void
    {
        $paymentId = $req->paymentId();
        $notificationType = $req->notificationType();

        if (blank($paymentId) || $notificationType !== 'payment') {
            Log::warning('Mercado Pago webhook ignored', [
                'payment_id' => $paymentId,
                'notification_type' => $notificationType,
            ]);
            return;
        }
    
        Log::info('Mercado Pago webhook received', [
            'payment_id' => $paymentId,
            'notification_type' => $notificationType,
        ]);

        $this->dispatcher->dispatchProcessMercadoPagoNotificationJob($paymentId);
    }
}
