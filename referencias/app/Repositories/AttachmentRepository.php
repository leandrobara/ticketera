<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\Attachment;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;


class AttachmentRepository
{

    public function create(array $data): Attachment
    {

        $attachment = new Attachment($data);
        $attachment->saveOrFail();
        return $attachment->fresh();
    }


    public function findOneByClientAndHashAndName(Client $client, string $hash, string $name): ?Attachment
    {
        return Attachment::where('client_id', $client->id)->where('hash', $hash)->where('name', $name)->first();
    }


    public function findOneByClientIdAndHashAndName(int $clientId, string $hash, string $name): ?Attachment
    {
        return Attachment::where('client_id', $clientId)->where('hash', $hash)->where('name', $name)->first();
    }


    public function findOneByClientAndHash(Client $client, string $hash): ?Attachment
    {
        return Attachment::where('client_id', $client->id)->where('hash', $hash)->first();
    }


    public function findByClientAndHashes(Client $client, Collection $hashes): Collection
    {
        return Attachment::where('client_id', $client->id)->whereIn('hash', $hashes)->get();
    }

}
