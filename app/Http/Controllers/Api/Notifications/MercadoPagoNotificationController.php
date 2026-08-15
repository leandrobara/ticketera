<?php

namespace App\Http\Controllers\Api\Notifications;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessMercadoPagoNotificationJob;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Notifications\MercadoPagoNotificationRequest;


class MercadoPagoNotificationController extends BaseAPIController
{

    public function handleNotification(MercadoPagoNotificationRequest $req): array
    {
        $paymentId = $req->paymentId();
        $notificationType = $req->notificationType();

        Log::info('Mercado Pago webhook received', [
            'payment_id' => $paymentId,
            'notification_type' => $notificationType,
            'queue_connection' => config('queue.default'),
            'query' => $req->query(),
            'body' => $req->all(),
        ]);

        if (filled($paymentId) && $notificationType === 'payment') {
            $notificationJob = new ProcessMercadoPagoNotificationJob($paymentId);

            $queuedJobId = Queue::push($notificationJob);

            Log::info('Mercado Pago webhook job queued', [
                'queue_job_id' => $queuedJobId,
                'payment_id' => $paymentId,
                'notification_type' => $notificationType,
                'queue_connection' => config('queue.default'),
            ]);
        } else {
            Log::warning('Mercado Pago webhook ignored', [
                'payment_id' => $paymentId,
                'notification_type' => $notificationType,
            ]);
        }

        return $this->getSuccessResponse([
            'received' => true,
        ]);
    }
}
