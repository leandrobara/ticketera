<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class MercadoPagoNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['nullable'],
            'id' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'data.id' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'action' => ['nullable', 'string'],
            'live_mode' => ['nullable', 'boolean'],
            'api_version' => ['nullable', 'string'],
            'date_created' => ['nullable', 'string'],
        ];
    }

    public function paymentId(): ?string
    {
        $paymentId = $this->input('data.id')
            ?? $this->query->get('data.id')
            ?? $this->query('id')
        ;

        return filled($paymentId) ? (string) $paymentId : null;
    }

    public function notificationType(): ?string
    {
        return $this->input('type')
            ?? $this->query('type')
            ?? $this->input('topic')
            ?? $this->query('topic')
        ;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->input('data');

        if (is_array($data) && array_key_exists('id', $data) && $data['id'] !== null) {
            $data['id'] = (string) $data['id'];
            $this->merge(['data' => $data]);
        }

        if ($this->has('id') && $this->input('id') !== null) {
            $this->merge(['id' => (string) $this->input('id')]);
        }
    }

    protected function passedValidation(): void
    {
        if ($this->hasValidSignature()) {
            return;
        }

        abort(401);
    }

    private function hasValidSignature(): bool
    {
        $secret = config('mercadopago.notification_secret');

        if (blank($secret)) {
            Log::warning('Mercado Pago notification secret is not configured');
            return true;
        }

        $signatureHeader = (string) $this->header('x-signature');
        $requestId = (string) $this->header('x-request-id');
        $dataId = (string) $this->paymentId();

        if (blank($signatureHeader) || blank($requestId) || blank($dataId)) {
            return false;
        }

        $signatureParts = $this->parseSignatureHeader($signatureHeader);
        $timestamp = $signatureParts['ts'] ?? null;
        $hash = $signatureParts['v1'] ?? null;

        if (blank($timestamp) || blank($hash)) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$timestamp};";
        $expectedHash = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expectedHash, $hash);
    }

    private function parseSignatureHeader(string $signatureHeader): array
    {
        $signatureParts = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if (filled($key) && filled($value)) {
                $signatureParts[$key] = $value;
            }
        }

        return $signatureParts;
    }
}
