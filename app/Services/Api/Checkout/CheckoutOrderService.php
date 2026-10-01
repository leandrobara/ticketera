<?php

namespace App\Services\Api\Checkout;

use App\Models\Order;
use App\Repositories\PresentationRepository;
use App\Repositories\SeasonTicketTypeRepository;
use App\Services\Api\MercadoPagoService;
use App\Services\Api\OrderItemPricingService;
use Illuminate\Support\Facades\DB;

class CheckoutOrderService
{
    public function __construct(
        private readonly BuyerService $buyerService,
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
        private readonly OrderItemService $orderItemService,
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly PresentationRepository $presentationRepository,
        private readonly OrderItemPricingService $orderItemPricingService,
        private readonly PresentationCapacityService $presentationCapacityService,
        private readonly SeasonTicketTypeRepository $seasonTicketTypeRepository,
    ) {
        //
    }

    public function create(array $data): ?string
    {
        $order = $this->createPendingOrder($data);
        $preference = $this->createMercadoPagoPreference(
            $order,
            $data['mercado_pago_device_id'] ?? null,
        );

        return $preference['init_point'] ?? null;
    }

    private function createPendingOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // Lock del tipo de ticket para no vender el mismo stock en paralelo.
            $ticketType = $this->seasonTicketTypeRepository->findAndLockOrFail(
                $data['season_ticket_type_id']
            );

            // Lock de la función y su temporada.
            $presentation = $this->presentationRepository->findAndLockWithSeasonOrFail(
                $data['presentation_id']
            );

            // ¿Hay cupo en la función y stock en este tipo de ticket?
            $this->presentationCapacityService->assertCanSellQuantity(
                $ticketType,
                $presentation,
                $data['quantity']
            );

            // Reusa el comprador por email o lo crea.
            $buyer = $this->buyerService->findOrCreate($data['buyer'], $presentation->season->show->organization_id);

            // Promo válida para este tipo de ticket, si mandaron código.
            $promotion = $this->orderItemPricingService->findApplicablePromotion(
                $ticketType,
                $data['promo_code'] ?? null
            );

            // Totales de la orden (tickets, descuento, service fee).
            $pricing = $this->orderItemPricingService->calculateAmountsForTicketType(
                $ticketType,
                $data['quantity'],
                $promotion
            );

            // Orden PENDING; los tickets se crean cuando MP aprueba.
            $order = $this->orderService->createPendingOrder(
                $buyer,
                $presentation,
                $pricing,
                $data
            );

            // Ítem (y snapshot de promo si aplica).
            $this->orderItemService->createItem(
                $order,
                $presentation,
                $ticketType,
                $pricing,
                $data['quantity'],
                $promotion,
            );

            // Relaciones que necesita createPreference.
            return $this->orderService->loadForMercadoPagoPreference($order);
        });
    }

    private function createMercadoPagoPreference(Order $order, ?string $mercadoPagoDeviceId): array
    {
        $preference = $this->mercadoPagoService->createPreference(
            $order,
            $mercadoPagoDeviceId,
        );

        $this->paymentService->createPendingMercadoPagoPayment($order, $preference);

        return $preference;
    }
}
