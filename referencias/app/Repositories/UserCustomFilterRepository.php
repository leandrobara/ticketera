<?php

namespace App\Repositories;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\UserCustomFilter;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\Repositories\Traits\VoidClearCache;
use App\DTO\UserCustomFilter\UserCustomFilterDTO;


class UserCustomFilterRepository implements Repository
{

    use VoidClearCache;


    public function findAllByClient(Client $client): Collection
    {
        return UserCustomFilter::where(['client_id' => $client->id])->orderBy('name')->get();
    }


    public function findAllByUserAndClient(User $user, Client $client): Collection
    {
        return UserCustomFilter::where(['client_id' => $client->id, 'user_id' => $user->id])->get();
    }


    public function create(UserCustomFilterDTO $dto): UserCustomFilter
    {
        $data = [
            'name' => $dto->name,
            'filters' => $dto->filters,
            'user_id' => $dto->user->id,
            'client_id' => $dto->client->id,
        ];
        $userCustomFilter = new UserCustomFilter($data);
        $userCustomFilter->saveOrFail();
        return $userCustomFilter;
    }


    public function update(UserCustomFilter $userCustomFilter, UserCustomFilterDTO $dto): UserCustomFilter
    {
        $data = ['filters' => $dto->filters, 'user_id' => $dto->user->id, 'client_id' => $dto->client->id];
        if ($dto->name) {
            $data['name'] = $dto->name;
        }
        $userCustomFilter->fill($data);
        $userCustomFilter->saveOrFail();
        return $userCustomFilter->fresh();
    }


    public function delete(UserCustomFilter $userCustomFilter): UserCustomFilter
    {
        $userCustomFilter->delete();
        return $userCustomFilter->fresh();
    }

}
