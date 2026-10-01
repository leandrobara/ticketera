<?php

namespace App\Services\Api\Checkout;

use App\Models\Buyer;
use App\Repositories\BuyerRepository;

class BuyerService
{
    public function __construct(
        private readonly BuyerRepository $buyerRepository,
    ) {
        //
    }

    public function findOrCreate(array $buyerData, int $organizationId): Buyer
    {
        $buyerData = $this->normalizeEmailAndPhone($buyerData);
        $buyerData['organization_id'] = $organizationId;
        $buyer = Buyer::withTrashed()->where('organization_id', $organizationId)->where('email', $buyerData['email'])->first();

        if (! $buyer) {
            return $this->buyerRepository->store($buyerData);
        }

        if ($buyer->trashed()) {
            $buyer = $this->buyerRepository->restore($buyer);
        }

        return $this->buyerRepository->update($buyer, $buyerData);
    }

    private function normalizeEmailAndPhone(array $buyerData): array
    {
        if (array_key_exists('email', $buyerData)) {
            $buyerData['email'] = mb_strtolower(trim($buyerData['email']));
        }

        if (array_key_exists('phone', $buyerData)) {
            $buyerData['phone'] = preg_replace('/\D+/', '', $buyerData['phone']);
        }

        return $buyerData;
    }
}
