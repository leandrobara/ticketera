<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return Payment::query()
            ->with('order.buyer')
            ->when($filters['order_id'] ?? null, fn ($query, int $orderId) => $query->where('order_id', $orderId))
            ->when($filters['provider'] ?? null, fn ($query, string $provider) => $query->where('provider', $provider))
            ->when($filters['provider_status'] ?? null, fn ($query, string $status) => $query->where('provider_status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('provider_payment_id', 'like', "%{$search}%")
                        ->orWhere('provider_preference_id', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($limit);
    }

    public function getOne(Payment $payment): Payment
    {
        return $payment->load('order.buyer');
    }

    public function store(array $attrs): Payment
    {
        return Payment::create($attrs)->load('order');
    }

    public function update(Payment $payment, array $attrs): Payment
    {
        $payment->update($attrs);
        return $payment->fresh('order');
    }

    public function delete(Payment $payment): void
    {
        $payment->delete();
    }
}
