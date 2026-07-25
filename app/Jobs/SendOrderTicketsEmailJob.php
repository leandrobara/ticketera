<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Api\OrderTicketsEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendOrderTicketsEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $orderId,
    ) {
        //
    }

    public function handle(OrderTicketsEmailService $orderTicketsEmailService): void
    {
        $order = $this->reserveOrderForSending();

        if (!$order) {
            return;
        }

        try {
            $messageId = $orderTicketsEmailService->send($order);

            $order->update([
                'tickets_email_sent_at' => now(),
                'tickets_email_sending_at' => null,
                'tickets_email_message_id' => $messageId,
            ]);
        } catch (\Throwable $exception) {
            $order->update([
                'tickets_email_sending_at' => null,
            ]);

            throw $exception;
        }
    }

    private function reserveOrderForSending(): ?Order
    {
        return DB::transaction(function () {
            $order = Order::query()
                ->with([
                    'buyer',
                    'items.promotionSnapshot',
                    'payments',
                    'presentation.season.show',
                    'presentation.season.venue',
                    'tickets.presentationTicketType',
                ])
                ->lockForUpdate()
                ->findOrFail($this->orderId)
            ;

            if ($order->status !== 'APPROVED') {
                return null;
            }

            if ($order->tickets_email_sent_at) {
                return null;
            }

            if (
                $order->tickets_email_sending_at
                && $order->tickets_email_sending_at->greaterThan(now()->subMinutes(10))
            ) {
                return null;
            }

            if ($order->tickets()->count() < $order->total_quantity) {
                return null;
            }

            $order->update([
                'tickets_email_sending_at' => now(),
            ]);

            return $order->fresh([
                'buyer',
                'items.promotionSnapshot',
                'payments',
                'presentation.season.show',
                'presentation.season.venue',
                'tickets.presentationTicketType',
            ]);
        });
    }
}
