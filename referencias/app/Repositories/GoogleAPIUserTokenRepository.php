<?php

namespace App\Repositories;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\GoogleAPIUserToken;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;


class GoogleAPIUserTokenRepository implements Repository
{

    public function find(int $id): ?GoogleAPIUserToken
    {
        return GoogleAPIUserToken::find($id);
    }


    public function findGmailTokenByUser(User $user): ?GoogleAPIUserToken
    {
        return GoogleAPIUserToken::where('user_id', $user->id)
            ->where('type', GoogleAPIUserToken::GMAIL_API_TYPE)
            ->first()
        ;
    }


    public function findOneAlreadyExistent(User $user, string $type, string $authCode): ?GoogleAPIUserToken
    {
        return GoogleAPIUserToken::where(['user_id' => $user->id, 'auth_code' => $authCode, 'type' => $type])->first();
    }


    public function findAllByClient(Client $client): Collection
    {
        return GoogleAPIUserToken::where(['client_id' => $client->id])->get();
    }


    public function findWithInvalidToken(): Collection
    {
        return GoogleAPIUserToken::where('json_token_string', 'like', '%invalid_grant%')->get();
    }


    public function create(array $data): GoogleAPIUserToken
    {
        $userToken = new GoogleAPIUserToken($data);
        $userToken->saveOrFail();
        return $userToken->fresh();
    }


    public function update(GoogleAPIUserToken $userToken, array $data): GoogleAPIUserToken
    {
        $userToken->fill($data)->saveOrFail();
        return $userToken->fresh();
    }


    public function delete(GoogleAPIUserToken $userToken): GoogleAPIUserToken
    {
        $userToken->delete();
        return $userToken->fresh();
    }

}
