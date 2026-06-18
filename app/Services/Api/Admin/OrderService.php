<?php

namespace App\Services\Api\Admin;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\Payment;
use App\Models\OrderItem;
use App\Models\OrderItemPromotion;
use App\Models\Presentation;
use App\Repositories\OrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\PresentationTicketType;
use App\Services\Api\OrderItemPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly BuyerService $buyerService,
        private readonly OrderItemPricingService $orderItemPricingService,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->orderRepository->listPaginated($filters);
    }

    public function getOne(Order $order): Order
    {
        return $this->orderRepository->getOne($order);
    }

    public function create(array $data): Order
    {
        $data['code'] = $data['code'] ?? 'ORD-'.Str::upper(Str::random(10));
        $data['currency'] = $data['currency'] ?? 'ARS';

        return $this->orderRepository->store($data);
    }

    public function createManual(array $data, ?int $createdByUserId = null): Order
    {
        return DB::transaction(function () use ($data, $createdByUserId) {
            $ticketType = PresentationTicketType::query()
                ->lockForUpdate()
                ->findOrFail($data['presentation_ticket_type_id']);

            $presentation = Presentation::query()
                ->lockForUpdate()
                ->find($ticketType->presentation_id);

            if (!$presentation) {
                throw ValidationException::withMessages([
                    'presentation_ticket_type_id' => ['presentation_ticket_type_without_presentation'],
                ]);
            }

            if ($ticketType->show_id !== $presentation->show_id) {
                throw ValidationException::withMessages([
                    'presentation_ticket_type_id' => ['presentation_ticket_type_show_mismatch'],
                ]);
            }

            if (!$ticketType->is_active) {
                throw ValidationException::withMessages([
                    'presentation_ticket_type_id' => ['presentation_ticket_type_not_active'],
                ]);
            }

            $this->validateManualAvailability($ticketType, $presentation->capacity, $data['quantity']);

            $buyer = $this->buyerService->create($data['buyer']);

            $promotion = $this->orderItemPricingService->resolvePromotion(
                $ticketType,
                $data['payment_method'] !== 'FREE',
                $data['promo_code'] ?? null
            );
            $pricing = $this->orderItemPricingService->calculateAmountsForTicketType(
                $ticketType,
                $data['quantity'],
                $promotion,
                $data['payment_method'] === 'FREE' ? '0.000000' : null
            );

            $order = Order::create([
                'notes' => $data['notes'] ?? null,
                'source' => 'ADMIN',
                'status' => 'APPROVED',
                'currency' => 'ARS',
                'approved_at' => now(),
                'buyer_id' => $buyer->id,
                'show_id' => $ticketType->show_id,
                'code' => $this->makeUniqueCode('ORD', Order::class),
                'payment_method' => $data['payment_method'],
                'total_amount' => $pricing['total_amount'],
                'total_quantity' => $data['quantity'],
                'presentation_id' => $ticketType->presentation_id,
                'created_by_user_id' => $createdByUserId,
            ]);

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'name' => $ticketType->name,
                'show_id' => $ticketType->show_id,
                'quantity' => $data['quantity'],
                'unit_price' => $pricing['unit_price'],
                'subtotal_amount' => $pricing['subtotal_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'total_amount' => $pricing['total_amount'],
                'presentation_ticket_type_id' => $ticketType->id,
            ]);

            if ($promotion) {
                OrderItemPromotion::create([
                    'order_item_id' => $orderItem->id,
                    'promotion_id' => $promotion->id,
                    'promotion_name' => $promotion->name,
                    'promotion_type' => $promotion->type,
                    'promotion_value' => $promotion->value,
                    'promotion_access_code' => $promotion->access_code,
                    'bundle_quantity' => $promotion->bundle_quantity,
                    'pay_quantity' => $promotion->pay_quantity,
                    'discount_amount' => $pricing['discount_amount'],
                ]);
            }

            Payment::create([
                'order_id' => $order->id,
                'paid_at' => now(),
                'currency' => 'ARS',
                'amount' => $pricing['total_amount'],
                'show_id' => $ticketType->show_id,
                'provider_status' => 'APPROVED',
                'provider' => $data['payment_method'],
                'raw_response' => ['source' => 'admin_manual'],
            ]);

            for ($i = 0; $i < $data['quantity']; $i++) {
                Ticket::create([
                    'status' => 'VALID',
                    'order_id' => $order->id,
                    'show_id' => $ticketType->show_id,
                    'order_item_id' => $orderItem->id,
                    'code' => $this->makeUniqueCode('TCK', Ticket::class),
                    'presentation_id' => $ticketType->presentation_id,
                    'presentation_ticket_type_id' => $ticketType->id,
                ]);
            }

            return $order->fresh([
                'buyer',
                'presentation',
                'createdByUser',
                'items.promotionSnapshot',
                'tickets',
                'payments',
            ]);
        });
    }

    public function update(Order $order, array $data): Order
    {
        return $this->orderRepository->update($order, $data);
    }

    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->with('tickets')
                ->lockForUpdate()
                ->findOrFail($order->id);

            Ticket::query()
                ->where('order_id', $order->id)
                ->where('status', 'VALID')
                ->lockForUpdate()
                ->update([
                    'status' => 'CANCELED',
                    'canceled_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->syncStatusFromTickets($order);

            return $this->getOne($order->fresh());
        });
    }

    public function syncStatusFromTickets(Order $order): Order
    {
        $hasActiveTickets = $order->tickets()
            ->whereIn('status', ['VALID', 'USED'])
            ->exists();

        if (!$hasActiveTickets) {
            $order->update([
                'status' => 'CANCELED',
            ]);
        }

        return $order->fresh();
    }

    public function delete(Order $order): void
    {
        $this->orderRepository->delete($order);
    }

    private function validateManualAvailability(PresentationTicketType $ticketType, int $presentationCapacity, int $quantity): void
    {
        $assignedPresentationTickets = Ticket::query()
            ->where('presentation_id', $ticketType->presentation_id)
            ->whereIn('status', ['VALID', 'USED'])
            ->lockForUpdate()
            ->count()
        ;

        if (($assignedPresentationTickets + $quantity) > $presentationCapacity) {
            throw ValidationException::withMessages([
                'quantity' => ['presentation_capacity_exceeded'],
            ]);
        }

        if ($ticketType->stock === null) {
            return;
        }

        $assignedTicketTypeTickets = Ticket::query()
            ->where('presentation_ticket_type_id', $ticketType->id)
            ->whereIn('status', ['VALID', 'USED'])
            ->lockForUpdate()
            ->count()
        ;

        if (($assignedTicketTypeTickets + $quantity) > $ticketType->stock) {
            throw ValidationException::withMessages([
                'quantity' => ['presentation_ticket_type_stock_exceeded'],
            ]);
        }
    }

    private function makeUniqueCode(string $prefix, string $modelClass): string
    {
        do {
            $code = $prefix.'-'.Str::upper(Str::random(10));
        } while ($modelClass::query()->where('code', $code)->exists());

        return $code;
    }
}
