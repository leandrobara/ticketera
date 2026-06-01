<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\Landing;
use App\Exceptions\DatabaseException;
use App\Repositories\Traits\VoidClearCache;


class LandingRepository implements Repository
{

    use VoidClearCache;


    public function findAllByClient(Client $client)
    {
        return Landing::where('client_id', $client->id)->get();
    }


    public function findOneByClientAndUrl(Client $client, string $url): ?Landing
    {
        $hash = Landing::buildHash($url);
        return Landing::where('hash', $hash)->where('client_id', $client->id)->first();
    }


    public function create(array $data): Landing
    {
        $data['hash'] = Landing::buildHash($data['url']);
        $landing = new Landing($data);
        $landing->saveOrFail();
        return $landing->fresh();
    }


    public function update(Landing $landing, array $data): Landing
    {
        if (isset($data['url'])) {
            $data['hash'] = Landing::buildHash($data['url']);
        }
        $landing->fill($data);
        $landing->saveOrFail();
        return $landing->fresh();
    }


    public function delete(Landing $landing): Landing
    {
        $landing->delete();
        return $landing->fresh();
    }

}
