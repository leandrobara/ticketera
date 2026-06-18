<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\OrderItem;
use App\Services\Api\Admin\OrderItemService;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\GetOrderItemRequest;
use App\Http\Requests\Admin\ListOrderItemRequest;
use App\Http\Requests\Admin\UpdateOrderItemRequest;
use App\Http\Requests\Admin\CreateOrderItemRequest;
use App\Http\Requests\Admin\DeleteOrderItemRequest;


class OrderItemController extends BaseAPIController
{

    public function list(ListOrderItemRequest $req): array
    {
        return $this->getSuccessResponse(resolve(OrderItemService::class)->list($req->validated()));
    }

    public function create(CreateOrderItemRequest $req): array
    {
        return $this->getSuccessResponse(resolve(OrderItemService::class)->create($req->validated()));
    }

    public function show(OrderItem $orderItem, GetOrderItemRequest $req): array
    {
        return $this->getSuccessResponse(resolve(OrderItemService::class)->getOne($orderItem));
    }

    public function update(OrderItem $orderItem, UpdateOrderItemRequest $req): array
    {
        return $this->getSuccessResponse(resolve(OrderItemService::class)->update($orderItem, $req->validated()));
    }

    public function delete(OrderItem $orderItem, DeleteOrderItemRequest $req): array
    {
        resolve(OrderItemService::class)->delete($orderItem);
        return $this->getSuccessResponse($orderItem);
    }
}
