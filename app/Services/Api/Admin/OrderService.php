<?php

namespace App\Services\Api\Admin;

use App\Jobs\SendOrderTicketsEmailJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemPromotion;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\SeasonTicketType;
use App\Models\Ticket;
use App\Repositories\OrderRepository;
use App\Services\Api\Checkout\PresentationCapacityService;
use App\Services\Api\OrderItemPricingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $order = DB::transaction(function () use ($data, $createdByUserId) {
            $ticketType = SeasonTicketType::query()
                ->lockForUpdate()
                ->findOrFail($data['season_ticket_type_id']);

            $presentation = Presentation::query()
                ->with('season')
                ->lockForUpdate()
                ->findOrFail($data['presentation_id']);

            app(PresentationCapacityService::class)->assertCanSellQuantity($ticketType, $presentation, $data['quantity']);

            $buyer = $this->buyerService->create($data['buyer']);

            $promotion = $data['payment_method'] === 'FREE'
                ? null
                : $this->orderItemPricingService->findApplicablePromotion(
                    $ticketType,
                    $data['promo_code'] ?? null,
                );
            $pricing = $this->orderItemPricingService->calculateAmountsForTicketType(
                $ticketType,
                $data['quantity'],
                $promotion,
                $data['payment_method'] === 'FREE' ? '0.000000' : null,
                false
            );

            $order = Order::create([
                'notes' => $data['notes'] ?? null,
                'source' => 'ADMIN',
                'status' => 'APPROVED',
                'currency' => 'ARS',
                'approved_at' => now(),
                'buyer_id' => $buyer->id,
                'show_id' => $presentation->season->show_id,
                'code' => $this->makeUniqueCode('ORD', Order::class),
                'payment_method' => $data['payment_method'],
                'total_amount' => $pricing['total_amount'],
                'total_quantity' => $data['quantity'],
                'presentation_id' => $presentation->id,
                'created_by_user_id' => $createdByUserId,
            ]);

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'name' => $ticketType->name,
                'show_id' => $presentation->season->show_id,
                'quantity' => $data['quantity'],
                'paid_quantity' => $pricing['paid_quantity'],
                'unit_price' => $pricing['unit_price'],
                'unit_service_fee' => $pricing['unit_service_fee'],
                'service_fee_type' => $pricing['service_fee_type'],
                'service_fee_fixed_amount' => $pricing['service_fee_fixed_amount'],
                'service_fee_percentage' => $pricing['service_fee_percentage'],
                'service_fee_base_amount' => $pricing['service_fee_base_amount'],
                'service_fee_minimum_applied' => $pricing['service_fee_minimum_applied'],
                'service_fee_minimum_unit_amount' => $pricing['service_fee_minimum_unit_amount'],
                'subtotal_amount' => $pricing['subtotal_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'service_fee_total_amount' => $pricing['service_fee_total_amount'],
                'total_amount' => $pricing['total_amount'],
                'season_ticket_type_id' => $ticketType->id,
            ]);

            if ($promotion) {
                OrderItemPromotion::create([
                    'order_item_id' => $orderItem->id,
                    'promotion_name' => $this->orderItemPricingService->getPromotionName($promotion),
                    'promotion_type' => $promotion->promotion_type,
                    'promotion_value' => $promotion->promotion_value,
                    'promotion_access_code' => $promotion->promotion_access_code,
                    'bundle_quantity' => $promotion->promotion_bundle_quantity,
                    'pay_quantity' => $promotion->promotion_pay_quantity,
                    'discount_amount' => $pricing['discount_amount'],
                ]);
            }

            Payment::create([
                'order_id' => $order->id,
                'paid_at' => now(),
                'currency' => 'ARS',
                'amount' => $pricing['total_amount'],
                'show_id' => $presentation->season->show_id,
                'provider_status' => 'APPROVED',
                'provider' => $data['payment_method'],
                'raw_response' => ['source' => 'admin_manual'],
            ]);

            for ($i = 0; $i < $data['quantity']; $i++) {
                Ticket::create([
                    'status' => 'VALID',
                    'order_id' => $order->id,
                    'show_id' => $presentation->season->show_id,
                    'order_item_id' => $orderItem->id,
                    'code' => $this->makeUniqueCode('TCK', Ticket::class),
                    'presentation_id' => $presentation->id,
                    'season_ticket_type_id' => $ticketType->id,
                ]);
            }

            $this->syncPresentationStatusFromCapacity($presentation);

            return $order->fresh([
                'buyer',
                'presentation',
                'createdByUser',
                'items.promotionSnapshot',
                'tickets',
                'payments',
            ]);
        });

        SendOrderTicketsEmailJob::dispatch($order->id)->afterCommit();

        return $order;
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
            $this->syncPresentationStatusFromCapacity(
                Presentation::query()
                    ->lockForUpdate()
                    ->findOrFail($order->presentation_id)
            );

            return $this->getOne($order->fresh());
        });
    }

    public function syncStatusFromTickets(Order $order): Order
    {
        $hasActiveTickets = $order->tickets()
            ->whereIn('status', ['VALID', 'USED'])
            ->exists();

        if (! $hasActiveTickets) {
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

    public function syncPresentationStatusFromCapacity(Presentation $presentation): void
    {
        $assignedTickets = Ticket::query()
            ->where('presentation_id', $presentation->id)
            ->whereIn('status', ['VALID', 'USED'])
            ->count();
        $remainingTickets = max(0, $presentation->capacity - $assignedTickets);

        if ($presentation->status === 'published' && $remainingTickets === 0) {
            $presentation->update([
                'status' => 'sold_out',
            ]);

            return;
        }

        if ($presentation->status === 'sold_out' && $remainingTickets > 0) {
            $presentation->update([
                'status' => 'published',
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
