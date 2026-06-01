<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Order;
use App\Services\Api\Admin\OrderService;
use App\Http\Requests\Admin\GetOrderRequest;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateOrderRequest;
use App\Http\Requests\Admin\DeleteOrderRequest;
use App\Http\Requests\Admin\ListOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;


class OrderController extends BaseAPIController
{
    public function list(ListOrderRequest $req): array
    {
        return $this->getSuccessResponse(resolve(OrderService::class)->list($req->validated()));
    }

    public function create(CreateOrderRequest $req): array
    {
        return $this->getSuccessResponse(resolve(OrderService::class)->create($req->validated()));
    }

    public function show(Order $order, GetOrderRequest $req): array
    {
        return $this->getSuccessResponse(resolve(OrderService::class)->getOne($order));
    }

    public function update(Order $order, UpdateOrderRequest $req): array
    {
        return $this->getSuccessResponse(resolve(OrderService::class)->update($order, $req->validated()));
    }

    public function delete(Order $order, DeleteOrderRequest $req): array
    {
        resolve(OrderService::class)->delete($order);
        return $this->getSuccessResponse($order);
    }
}

