<?php

namespace App\Services\Api\Admin;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->userRepository->listPaginated($filters, $filters['per_page'] ?? 20);
    }

    public function getOne(User $user): User
    {
        return $user;
    }

    public function create(array $data): User
    {
        $data = $this->normalizeData($data);

        return $this->userRepository->store($data);
    }

    public function update(User $user, array $data): User
    {
        $data = $this->normalizeData($data);

        if (($data['password'] ?? null) === null || $data['password'] === '') {
            unset($data['password']);
        }

        return $this->userRepository->update($user, $data);
    }

    public function delete(User $user): void
    {
        $this->userRepository->delete($user);
    }

    private function normalizeData(array $data): array
    {
        if (array_key_exists('email', $data)) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }

        return $data;
    }
}
