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

    public function findOrCreate(array $buyerData): Buyer
    {
        $buyerData = $this->normalizeEmailAndPhone($buyerData);
        $buyer = $this->buyerRepository->findByEmailWithTrashed($buyerData['email']);

        if (!$buyer) {
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
