<?php

namespace App\Services\Api\Admin;

use App\Models\SeasonTicketType;
use App\Repositories\SeasonTicketTypeRepository;
use App\Support\TicketTypeData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class SeasonTicketTypeService
{
    public function __construct(
        private readonly SeasonTicketTypeRepository $seasonTicketTypeRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->seasonTicketTypeRepository->listPaginated($filters);
    }

    public function getOne(SeasonTicketType $seasonTicketType): SeasonTicketType
    {
        return $this->seasonTicketTypeRepository->getOne($seasonTicketType);
    }

    public function create(array $data): SeasonTicketType
    {
        $data = TicketTypeData::validate($data);
        $data = $this->normalizePromotionData($data);
        $this->validatePromotionBusinessRules($data);

        return $this->seasonTicketTypeRepository->store($data);
    }

    public function update(SeasonTicketType $seasonTicketType, array $data): SeasonTicketType
    {
        $data = TicketTypeData::validate($data, $seasonTicketType);
        $data = $this->normalizePromotionData($data);
        $this->validatePromotionBusinessRules([
            ...$seasonTicketType->toArray(),
            ...$data,
        ]);

        return $this->seasonTicketTypeRepository->update($seasonTicketType, $data);
    }

    public function delete(SeasonTicketType $seasonTicketType): void
    {
        $this->seasonTicketTypeRepository->delete($seasonTicketType);
    }

    private function normalizePromotionData(array $data): array
    {
        if (array_key_exists('promotion_access_code', $data)) {
            $accessCode = trim((string) $data['promotion_access_code']);
            $data['promotion_access_code'] = $accessCode === '' ? null : mb_strtolower($accessCode);
        }

        $promotionType = $data['promotion_type'] ?? null;
        $hasPromotion = filled($promotionType);

        if (array_key_exists('promotion_is_active', $data)) {
            $data['promotion_is_active'] = (bool) $data['promotion_is_active'];
        } elseif ($hasPromotion) {
            $data['promotion_is_active'] = true;
        }

        if (! $hasPromotion && array_key_exists('promotion_type', $data)) {
            $data['promotion_name'] = null;
            $data['promotion_value'] = null;
            $data['promotion_bundle_quantity'] = null;
            $data['promotion_pay_quantity'] = null;
            $data['promotion_access_code'] = null;
            $data['promotion_is_active'] = false;

            return $data;
        }

        if ($promotionType === 'buy_x_get_y') {
            $data['promotion_value'] = null;
        }

        if (in_array($promotionType, ['percent_discount', 'fixed_discount'], true)) {
            $data['promotion_bundle_quantity'] = null;
            $data['promotion_pay_quantity'] = null;
        }

        return $data;
    }

    private function validatePromotionBusinessRules(array $data): void
    {
        if (blank($data['promotion_type'] ?? null)) {
            return;
        }

        if (
            ($data['promotion_type'] ?? null) === 'fixed_discount'
            && (float) ($data['promotion_value'] ?? 0) > (float) ($data['price'] ?? 0)
        ) {
            throw ValidationException::withMessages([
                'promotion_value' => ['fixed_discount_exceeds_ticket_type_price'],
            ]);
        }
    }
}
