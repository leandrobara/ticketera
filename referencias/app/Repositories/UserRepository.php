<?php

namespace App\Repositories;

use Exception;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\Repositories\Traits\VoidClearCache;


class UserRepository implements Repository
{

    use VoidClearCache;

    
    public function findAllByClient(Client $client): Collection
    {
        return User::where(['client_id' => $client->id])->get();
    }


    public function findAllEnabledByClient(Client $client): Collection
    {
        return User::where(['client_id' => $client->id, 'enabled' => true])->get();
    }


    public function findAllEnabledByClientIds(Collection $clientIds, array $opts = []): Collection
    {
        $fields = $opts['fields'] ?? [];
        return User::whereIn('client_id', $clientIds)->where('enabled', true)->get($fields);
    }


    public function find(int $id): ?User
    {
        return User::find($id);
    }


    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }


    public function findOneByClientAndAPIToken(Client $client, string $apiToken): ?User
    {
        $user = User::where('api_token', $apiToken)->where('client_id', $client->id)->first();
        return $user;
    }

    public function findByWAPISessionPhoneNumber(string $wapiSessionPhoneNumber): ?Collection
    {
        $users = User::where('wapi_session_phone_number', $wapiSessionPhoneNumber)->get();
        return $users;
    }


    public function findByEmailOrUsername(Client $client, string $emailOrUsername): ?User
    {
        return User::where(['client_id' => $client->id])
            ->where(function ($query) use ($emailOrUsername) {
                $query->where(['username' => $emailOrUsername])->orWhere(['email'  => $emailOrUsername]);
            })
            ->first()
        ;
    }


    public function findSuperUser(string $username, string $password): ?User
    {
        return User::where('is_superuser', true)
            ->where('superuser_username', $username)
            ->first()
        ;
    }


    public function findFirstAdminByClient(Client $client): ?User
    {
        return User::where('client_id', $client->id)->where('type', 'admin')->orderBy('id')->first();
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return User::whereIn('id', $ids)->where('client_id', $client->id)->get();
    }


    public function findOneByUserIdAndClientId(int $userId, int $clientId): ?User
    {
        return User::where('id', $userId)->where('client_id', $clientId)->first();
    }


    public function findWithGmailAPIEnabled(): Collection
    {
        $builder = User::whereHas('client', function ($q) {
            $q->where('enabled', true);
        });
        $builder->whereHas('client.clientSettings', function ($q) {
            $q->where('enable_google_gmail_api', true);
        });
        $builder->where('enabled', true);
        $builder->whereHas('googleGmailAPIUserToken');
        return $builder->get();
    }


    public function resetLoginSessions(Collection $users): bool
    {
        foreach ($users as $user) {
            $attrs['api_token'] = Str::uuid();
            $attrs['remember_token'] = Str::uuid();
            $attrs['api_token_expiration_date'] = null;
            
            $this->update($user, $attrs);
        }
        return true;
    }


    public function createNewClientDefault(Client $client, array $attrs): User
    {
        $attrs['client_id'] = $client->id;
        $user = User::factory()->newClientDefault()->create($attrs);
        return $user;
    }


    public function create(array $attrs): User
    {
        $attrs['password'] = password_hash($attrs['password'], PASSWORD_DEFAULT);
        $user = new User();
        $user->fill($attrs);
        $user->saveOrFail();
        return $user->fresh();
    }


    public function update(User $user, array $attrs): User
    {
        if (isset($attrs['password'])) {
            $attrs['password'] = password_hash($attrs['password'], PASSWORD_DEFAULT);
        }
        $user->fill($attrs);
        $user->saveOrFail();
        return $user->fresh();
    }

}
