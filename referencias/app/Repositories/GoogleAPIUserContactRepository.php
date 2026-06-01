<?php

namespace App\Repositories;

use Exception;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use App\Models\GoogleAPIUserContact;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;


class GoogleAPIUserContactRepository implements Repository
{

    public function find(int $id): ?GoogleAPIUserContact
    {
        return GoogleAPIUserContact::find($id);
    }


    public function findAllByClient(Client $client): Collection
    {
        return GoogleAPIUserContact::where(['client_id' => $client->id])->get();
    }


    public function findAllByUser(User $user): Collection
    {
        return GoogleAPIUserContact::where(['user_id' => $user->id])->get();
    }


    public function create(array $data): GoogleAPIUserContact
    {
        $googleContact = new GoogleAPIUserContact($data);
        $googleContact->saveOrFail();
        return $googleContact->fresh();
    }


    public function update(GoogleAPIUserContact $googleContact, array $data): GoogleAPIUserContact
    {
        $googleContact->fill($data)->saveOrFail();
        return $googleContact->fresh();
    }


    public function delete(GoogleAPIUserContact $googleContact): GoogleAPIUserContact
    {
        $googleContact->delete();
        return $googleContact->fresh();
    }

}
