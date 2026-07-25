<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class MercadoPagoNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string'],
            'api_version' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'data.id' => ['nullable', 'string'],
            'date_created' => ['nullable', 'string'],
            'id' => ['nullable', 'string'],
            'live_mode' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string'],
            'user_id' => ['nullable'],
        ];
    }

    public function paymentId(): ?string
    {
        return $this->input('data.id')
            ?? $this->query->get('data.id')
            ?? $this->query('id');
    }

    public function notificationType(): ?string
    {
        return $this->input('type')
            ?? $this->query('type')
            ?? $this->input('topic')
            ?? $this->query('topic');
    }
}
