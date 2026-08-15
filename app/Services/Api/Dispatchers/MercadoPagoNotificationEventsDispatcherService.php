<?php

namespace App\Services\Api\Dispatchers;

use App\Jobs\SendOrderTicketsEmailJob;
use App\Jobs\ProcessMercadoPagoNotificationJob;


class MercadoPagoNotificationEventsDispatcherService
{
    
    public function dispatchProcessMercadoPagoNotificationJob(string $paymentId, ?int $delaySecs = null): void
    {
        $pendingDispatch = ProcessMercadoPagoNotificationJob::dispatch($paymentId);

        if ($delaySecs !== null) {
            $pendingDispatch->delay(now()->addSeconds($delaySecs));
        }
    }

    public function dispatchSendOrderTicketsEmailJob(int $orderId): void
    {
        SendOrderTicketsEmailJob::dispatch($orderId);
    }
}
