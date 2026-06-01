<?php

namespace App\Services\Api;

use App\Exceptions\Services\AuthService\AuthorizationException;
use App\Models\AdminAccessToken;
use App\Models\User;
use App\Repositories\AdminAccessTokenRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AdminAccessTokenRepository $adminAccessTokenRepository,
    ) {
    }

    public function loginUser(array $credentials): array
    {
        $user = $this->userRepository->findByEmail($credentials['email']);
        if (!$this->isValidAdminLogin($user, $credentials['password'])) {
            throw new AuthorizationException('invalid_user_or_password');
        }

        $token = Str::random(80);
        $this->adminAccessTokenRepository->createForUser($user, $token);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function logoutUser(?AdminAccessToken $accessToken): array
    {
        $this->adminAccessTokenRepository->delete($accessToken);

        return [
            'message' => 'Logged out.',
        ];
    }

    private function isValidAdminLogin(?User $user, string $password): bool
    {
        return $user !== null
            && Hash::check($password, $user->password)
            && $user->role === 'admin';
    }
}
