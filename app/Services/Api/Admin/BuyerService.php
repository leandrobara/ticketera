<?php

namespace App\Services\Api\Admin;

use App\Models\Buyer;
use App\Repositories\BuyerRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuyerService
{
    public function __construct(
        private readonly BuyerRepository $buyerRepository,
    ) {
        //
    }


    public function list(array $filters): LengthAwarePaginator
    {
        return $this->buyerRepository->listPaginated($filters['search'] ?? null);
    }


    public function getOne(Buyer $buyer): Buyer
    {
        return $buyer;
    }


    public function create(array $data): Buyer
    {
        $data = $this->normalizeEmailAndPhone($data);
        $buyer = $this->buyerRepository->findByEmailWithTrashed($data['email']);

        if ($buyer) {
            if ($buyer->trashed()) {
                $buyer = $this->buyerRepository->restore($buyer);
            }

            return $this->update($buyer, $data);
        }

        return $this->buyerRepository->store($data);
    }


    public function update(Buyer $buyer, array $data): Buyer
    {
        $data = $this->normalizeEmailAndPhone($data);
        return $this->buyerRepository->update($buyer, $data);
    }


    public function delete(Buyer $buyer): void
    {
        $this->buyerRepository->delete($buyer);
    }


    private function normalizeEmailAndPhone(array $data): array
    {
        if (array_key_exists('email', $data)) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        if (array_key_exists('phone', $data)) {
            $data['phone'] = preg_replace('/\D+/', '', $data['phone']);
        }

        return $data;
    }
}
