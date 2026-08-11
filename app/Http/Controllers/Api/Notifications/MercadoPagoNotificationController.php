<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Jobs\ProcessMercadoPagoNotificationJob;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Notifications\MercadoPagoNotificationRequest;

class MercadoPagoNotificationController extends BaseAPIController
{
    public function handleNotification(MercadoPagoNotificationRequest $req): array
    {
        $paymentId = $req->paymentId();
        $notificationType = $req->notificationType();

        if (
            filled($paymentId)
            && in_array($notificationType, ['payment', 'merchant_order'], true)
        ) {
            ProcessMercadoPagoNotificationJob::dispatch(
                $paymentId,
                $notificationType,
            );
        }

        return $this->getSuccessResponse([
            'received' => true,
        ]);
    }
}
