<?php

namespace App\Services\Api;

use App\Models\Order;
use RuntimeException;

class OrderTicketsEmailService
{
    public function __construct(
        private readonly BrevoTransactionalEmailService $brevoTransactionalEmailService,
    ) {
        //
    }

    public function send(Order $order): string
    {
        if (blank(config('brevo.sender.email'))) {
            throw new RuntimeException('brevo_sender_email_not_configured');
        }

        $order->loadMissing([
            'buyer',
            'items.promotionSnapshot',
            'payments',
            'presentation.season.show',
            'presentation.season.venue',
            'tickets.presentationTicketType',
        ]);

        $response = $this->brevoTransactionalEmailService->send([
            'sender' => [
                'name' => config('brevo.sender.name'),
                'email' => config('brevo.sender.email'),
            ],
            'to' => [
                [
                    'name' => trim($order->buyer->name.' '.$order->buyer->last_name),
                    'email' => $order->buyer->email,
                ],
            ],
            'subject' => 'Tus entradas para '.$order->presentation->season->show->title,
            'htmlContent' => view('emails.order-tickets', [
                'order' => $order,
            ])->render(),
        ]);

        return $response['messageId'] ?? '';
    }
}
