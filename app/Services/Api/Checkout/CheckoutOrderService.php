<?php

namespace App\Services\Api\Checkout;

use App\Models\Presentation;
use App\Models\PresentationTicketType;
use App\Models\Ticket;
use App\Services\Api\Admin\BuyerService;
use App\Services\Api\MercadoPagoService;
use App\Services\Api\OrderItemPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutOrderService
{
    public function __construct(
        private readonly BuyerService $buyerService,
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly OrderItemPricingService $orderItemPricingService,
        private readonly OrderService $orderService,
        private readonly OrderItemService $orderItemService,
        private readonly PaymentService $paymentService,
    ) {
        //
    }

    public function create(array $data): array
    {
        $order = DB::transaction(function () use ($data) {
            $ticketType = PresentationTicketType::query()
                ->lockForUpdate()
                ->findOrFail($data['presentation_ticket_type_id'])
            ;

            $presentation = Presentation::query()
                ->with('season')
                ->lockForUpdate()
                ->findOrFail($ticketType->presentation_id)
            ;

            $this->validateAvailability($ticketType, $presentation->capacity, $data['quantity']);

            $buyer = $this->buyerService->create($data['buyer']);
            $promotion = $this->orderItemPricingService->findApplicablePromotion(
                $ticketType,
                $data['promo_code'] ?? null
            );
            
            $pricing = $this->orderItemPricingService->calculateAmountsForTicketType(
                $ticketType,
                $data['quantity'],
                $promotion
            );

            $order = $this->orderService->createPendingOrder(
                $buyer,
                $presentation,
                $pricing,
                $data
            );

            $this->orderItemService->createItem(
                $order,
                $presentation,
                $ticketType,
                $pricing,
                $data['quantity'],
                $promotion,
            );

            return $order->fresh([
                'buyer',
                'presentation.season.show',
                'items',
            ]);
        });

        $preference = $this->mercadoPagoService->createPreference(
            $order,
            $data['mercado_pago_device_id'] ?? null,
        );

        $this->paymentService->createPendingMercadoPagoPayment(
            $order,
            $preference,
        );

        return [
            'preference' => [
                'init_point' => $preference['init_point'] ?? null,
            ],
        ];
    }

    private function validateAvailability(PresentationTicketType $ticketType, int $presentationCapacity, int $quantity): void
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

}
