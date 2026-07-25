<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Api\BaseAPIController;
use App\Services\Api\Notifications\MercadoPagoNotificationService;
use App\Http\Requests\Notifications\MercadoPagoNotificationRequest;

class MercadoPagoNotificationController extends BaseAPIController
{
    public function handleNotification(MercadoPagoNotificationRequest $req): array
    {
        resolve(MercadoPagoNotificationService::class)->handleNotification(
            $req->validated(),
            $req->paymentId(),
            $req->notificationType()
        );

        return $this->getSuccessResponse([
            'received' => true,
        ]);
    }
}
