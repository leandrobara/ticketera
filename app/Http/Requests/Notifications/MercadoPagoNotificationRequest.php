<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

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
        return $this->input('data.id')
            ?? $this->query->get('data.id')
            ?? $this->query('id')
        ;
    }

    public function notificationType(): ?string
    {
        return $this->input('type')
            ?? $this->query('type')
            ?? $this->input('topic')
            ?? $this->query('topic')
        ;
    }
}
