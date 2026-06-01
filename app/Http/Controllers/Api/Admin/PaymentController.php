<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreatePaymentRequest;
use App\Http\Requests\Admin\DeletePaymentRequest;
use App\Http\Requests\Admin\GetPaymentRequest;
use App\Http\Requests\Admin\ListPaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Models\Payment;
use App\Services\Api\Admin\PaymentService;

class PaymentController extends BaseAPIController
{
    public function list(ListPaymentRequest $req): array
    {
        return $this->getSuccessResponse(resolve(PaymentService::class)->list($req->validated()));
    }

    public function create(CreatePaymentRequest $req): array
    {
        return $this->getSuccessResponse(resolve(PaymentService::class)->create($req->validated()));
    }

    public function show(Payment $payment, GetPaymentRequest $req): array
    {
        return $this->getSuccessResponse(resolve(PaymentService::class)->getOne($payment));
    }

    public function update(Payment $payment, UpdatePaymentRequest $req): array
    {
        return $this->getSuccessResponse(resolve(PaymentService::class)->update($payment, $req->validated()));
    }

    public function delete(Payment $payment, DeletePaymentRequest $req): array
    {
        resolve(PaymentService::class)->delete($payment);
        return $this->getSuccessResponse($payment);
    }
}

