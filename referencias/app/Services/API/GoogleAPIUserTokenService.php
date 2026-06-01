<?php

namespace App\Services\API;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use App\Models\GoogleAPIUserToken;
use Illuminate\Support\Collection;
use App\Services\Traits\GetClientFromRequest;


class GoogleAPIUserTokenService
{

    use GetClientFromRequest;

    private $googleAPIUserTokenRepository;


    public function __construct(Repository $googleAPIUserTokenRepository)
    {
        $this->googleAPIUserTokenRepository = $googleAPIUserTokenRepository;
    }


    public function find(int $id): ?GoogleAPIUserToken
    {
        return $this->googleAPIUserTokenRepository->find($id);
    }


    public function findAllByClient(Client $client): Collection
    {
        return $this->googleAPIUserTokenRepository->findAllByClient($client);
    }


    public function findGmailTokenByUser(User $user): ?GoogleAPIUserToken
    {
        return $this->googleAPIUserTokenRepository->findGmailTokenByUser($user);
    }


    public function findOneAlreadyExistent(User $user, string $type, string $authCode): ?GoogleAPIUserToken
    {
        return $this->googleAPIUserTokenRepository->findOneAlreadyExistent($user, $authCode, $type);
    }


    public function findWithInvalidToken(): Collection
    {
        return $this->googleAPIUserTokenRepository->findWithInvalidToken();
    }


    public function create($data): GoogleAPIUserToken
    {
        $user = $data['user'] ?? $this->getUser();
        $client = $data['client'] ?? $this->getClient();
        unset($data['user']);
        unset($data['client']);
        $data['user_id'] = $user->id;
        $data['client_id'] = $client->id;
        $googleAPIUserToken = $this->googleAPIUserTokenRepository->create($data);
        return $googleAPIUserToken;
    }


    public function update(GoogleAPIUserToken $googleAPIUserToken, array $data): GoogleAPIUserToken
    {
        return $this->googleAPIUserTokenRepository->update($googleAPIUserToken, $data);
    }


    public function delete(GoogleAPIUserToken $googleAPIUserToken): GoogleAPIUserToken
    {
        return $this->googleAPIUserTokenRepository->delete($googleAPIUserToken);
    }


    public function deleteByUserAndType(User $user, string $tokenType): ?GoogleAPIUserToken
    {
        $isGmailToken = $tokenType == GoogleAPIUserToken::GMAIL_API_TYPE;
        $isPeopleToken = $tokenType == GoogleAPIUserToken::PEOPLE_API_TYPE;
        if (!$isGmailToken && !$isPeopleToken) {
            throw new Exception('Invalid token type');
        }
        $googleAPIUserToken = $isGmailToken ? $user->googleGmailAPIUserToken : $user->googlePeopleAPIUserToken;
        if ($googleAPIUserToken) {
            $googleAPIUserToken = $this->delete($googleAPIUserToken);
        }
        return $googleAPIUserToken;
    }

}