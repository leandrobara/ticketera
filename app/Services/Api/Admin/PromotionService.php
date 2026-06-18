<?php

namespace App\Services\Api\Admin;

use App\Models\Promotion;
use App\Models\PresentationTicketType;
use App\Repositories\PromotionRepository;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function __construct(
        private readonly PromotionRepository $promotionRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->promotionRepository->listPaginated($filters);
    }

    public function getOne(Promotion $promotion): Promotion
    {
        return $this->promotionRepository->getOne($promotion);
    }

    public function create(array $data): Promotion
    {
        $data = $this->normalize($data);
        $this->validateFixedDiscount($data);
        $this->validateTicketTypePromotionAvailability(
            $data['presentation_ticket_type_id'],
            null,
            $data['is_active'] ?? true
        );

        return $this->promotionRepository->store($data);
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        $data = $this->normalize($data, $promotion->type);
        $promotionData = [
            'type' => $data['type'] ?? $promotion->type,
            'value' => $data['value'] ?? $promotion->value,
            'is_active' => $data['is_active'] ?? $promotion->is_active,
            'presentation_ticket_type_id' => $data['presentation_ticket_type_id']
                ?? $promotion->presentation_ticket_type_id,
        ];
        $this->validateFixedDiscount($promotionData);
        $this->validateTicketTypePromotionAvailability(
            $promotionData['presentation_ticket_type_id'],
            $promotion->id,
            $promotionData['is_active']
        );

        return $this->promotionRepository->update($promotion, $data);
    }

    public function delete(Promotion $promotion): void
    {
        $this->promotionRepository->delete($promotion);
    }

    private function normalize(array $data, ?string $currentType = null): array
    {
        if (array_key_exists('access_code', $data)) {
            $accessCode = trim((string) $data['access_code']);
            $data['access_code'] = $accessCode === '' ? null : mb_strtolower($accessCode);
        }

        $type = $data['type'] ?? $currentType;

        if ($type === 'buy_x_get_y') {
            $data['value'] = null;
        } else {
            $data['bundle_quantity'] = null;
            $data['pay_quantity'] = null;
        }

        return $data;
    }

    private function validateFixedDiscount(array $data): void
    {
        if (($data['type'] ?? null) !== 'fixed_discount') {
            return;
        }

        $discount = BigDecimal::of($data['value']);
        $ticketType = PresentationTicketType::findOrFail($data['presentation_ticket_type_id']);

        if ($discount->isGreaterThan(BigDecimal::of($ticketType->price))) {
            throw ValidationException::withMessages([
                'value' => [
                    "The fixed discount cannot exceed the price of {$ticketType->name}.",
                ],
            ]);
        }
    }

    private function validateTicketTypePromotionAvailability(
        int $ticketTypeId,
        ?int $ignorePromotionId = null,
        bool $isActive = true
    ): void {
        if (!$isActive) {
            return;
        }

        $exists = Promotion::query()
            ->where('presentation_ticket_type_id', $ticketTypeId)
            ->where('is_active', true)
            ->when($ignorePromotionId, fn ($query) => $query->where('id', '!=', $ignorePromotionId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'presentation_ticket_type_id' => [
                    'presentation_ticket_type_already_has_active_promotion',
                ],
            ]);
        }
    }
}
