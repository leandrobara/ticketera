<?php

namespace App\Services\Api;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MercadoPagoService
{
    public function getMerchantOrder(string $merchantOrderId): array
    {
        $accessToken = config('mercadopago.access_token');

        if (blank($accessToken)) {
            throw new RuntimeException('mercado_pago_access_token_not_configured');
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://api.mercadopago.com/merchant_orders/{$merchantOrderId}")
        ;

        if ($response->failed()) {
            Log::error('Mercado Pago merchant order fetch failed', [
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
                'merchant_order_id' => $merchantOrderId,
            ]);

            throw new RuntimeException(
                'mercado_pago_merchant_order_fetch_failed: '.$response->status().' '.$response->body()
            );
        }

        return $response->json();
    }

    public function getPayment(string $paymentId): array
    {
        $accessToken = config('mercadopago.access_token');

        if (blank($accessToken)) {
            throw new RuntimeException('mercado_pago_access_token_not_configured');
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}")
        ;

        if ($response->failed()) {
            Log::error('Mercado Pago payment fetch failed', [
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
                'payment_id' => $paymentId,
            ]);

            throw new RuntimeException(
                'mercado_pago_payment_fetch_failed: '.$response->status().' '.$response->body()
            );
        }

        return $response->json();
    }

    public function createPreference(Order $order): array
    {
        $accessToken = config('mercadopago.access_token');

        if (blank($accessToken)) {
            throw new RuntimeException('mercado_pago_access_token_not_configured');
        }

        $order->loadMissing(['buyer', 'presentation.season.show', 'items']);

        $payload = $this->buildPreferencePayload($order);

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post('https://api.mercadopago.com/checkout/preferences', $payload)
        ;

        if ($response->failed()) {
            Log::error('Mercado Pago preference creation failed', [
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
                'order_id' => $order->id,
                'order_code' => $order->code,
            ]);

            throw new RuntimeException(
                'mercado_pago_preference_creation_failed: '.$response->status().' '.$response->body()
            );
        }

        return $response->json();
    }

    private function buildPreferencePayload(Order $order): array
    {
        $payload = [
            'items' => $this->buildPreferenceItems($order),
            'payer' => [
                'name' => $order->buyer?->name,
                'surname' => $order->buyer?->last_name,
                'email' => $order->buyer?->email,
            ],
            'back_urls' => [
                'success' => $this->buildBackUrl(config('mercadopago.urls.success'), $order),
                'failure' => $this->buildBackUrl(config('mercadopago.urls.failure'), $order),
                'pending' => $this->buildBackUrl(config('mercadopago.urls.pending'), $order),
            ],
            'auto_return' => 'approved',
            'external_reference' => $order->code,
            'metadata' => [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'show_id' => $order->show_id,
                'presentation_id' => $order->presentation_id,
            ],
        ];

        if (filled(config('mercadopago.urls.notification'))) {
            $payload['notification_url'] = config('mercadopago.urls.notification');
        }

        return $payload;
    }

    private function buildBackUrl(?string $url, Order $order): string
    {
        if (blank($url)) {
            return '';
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        $season = $order->presentation?->season;
        $show = $season?->show;
        $backUrl = $show ? url("/shows/{$season->id}/{$show->slug}") : url('/');

        return $url.$separator.http_build_query([
            'order' => $order->code,
            'back_url' => $backUrl,
        ]);
    }

    private function buildPreferenceItems(Order $order): array
    {
        return $order->items
            ->flatMap(function ($item) use ($order) {
                $quantity = max(1, (int) $item->quantity);
                $paidQuantity = max(1, (int) ($item->paid_quantity ?? $quantity));
                $ticketAmount = ((float) $item->subtotal_amount) - ((float) $item->discount_amount);
                $serviceFeeAmount = (float) ($item->service_fee_total_amount ?? 0);
                $items = [
                    [
                        'id' => (string) $item->id,
                        'title' => $item->name,
                        'description' => $order->presentation?->season?->show?->title,
                        'quantity' => $quantity,
                        'currency_id' => $order->currency,
                        'unit_price' => round($ticketAmount / $quantity, 2),
                    ],
                ];

                if ($serviceFeeAmount > 0) {
                    $items[] = [
                        'id' => $item->id.'-service-fee',
                        'title' => 'Costo por servicio',
                        'description' => $item->name,
                        'quantity' => $paidQuantity,
                        'currency_id' => $order->currency,
                        'unit_price' => round($serviceFeeAmount / $paidQuantity, 2),
                    ];
                }

                return $items;
            })
            ->values()
            ->all();
    }
}
