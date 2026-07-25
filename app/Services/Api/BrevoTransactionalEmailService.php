<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BrevoTransactionalEmailService
{
    public function send(array $payload): array
    {
        $apiKey = config('brevo.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('brevo_api_key_not_configured');
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
        ])
            ->acceptJson()
            ->asJson()
            ->post(config('brevo.transactional_email_url'), $payload)
        ;

        if ($response->failed()) {
            Log::error('Brevo transactional email failed', [
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException(
                'brevo_transactional_email_failed: '.$response->status().' '.$response->body()
            );
        }

        return $response->json();
    }
}
