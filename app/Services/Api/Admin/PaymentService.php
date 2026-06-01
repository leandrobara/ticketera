<?php

namespace App\Services\Api\Admin;

use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->paymentRepository->listPaginated($filters);
    }

    public function getOne(Payment $payment): Payment
    {
        return $this->paymentRepository->getOne($payment);
    }

    public function create(array $data): Payment
    {
        $data['provider'] = $data['provider'] ?? 'MERCADO_PAGO';
        $data['currency'] = $data['currency'] ?? 'ARS';

        return $this->paymentRepository->store($data);
    }

    public function update(Payment $payment, array $data): Payment
    {
        return $this->paymentRepository->update($payment, $data);
    }

    public function delete(Payment $payment): void
    {
        $this->paymentRepository->delete($payment);
    }
}
