<?php

namespace App\Repositories;

use App\Models\Client;
use Illuminate\Support\Collection;
use App\Models\WhatsAppQuickResponse;
use App\Repositories\Traits\VoidClearCache;


class WhatsAppQuickResponseRepository implements Repository
{

    use VoidClearCache;


    public function findById(int $id): ?WhatsAppQuickResponse
    {
        return WhatsAppQuickResponse::find($id);
    }


    public function findAllByClient(Client $client): Collection
    {
        return WhatsAppQuickResponse::where('client_id', $client->id)->get();
    }


    public function create(array $data): WhatsAppQuickResponse
    {
        $quickResponse = new WhatsAppQuickResponse($data);
        $quickResponse->saveOrFail();
        return $quickResponse->fresh();
    }


    public function update(WhatsAppQuickResponse $quickResponse, array $data): WhatsAppQuickResponse
    {
        $quickResponse->fill($data);
        $quickResponse->saveOrFail();
        return $quickResponse->fresh();
    }


    public function delete(WhatsAppQuickResponse $quickResponse): WhatsAppQuickResponse
    {
        $quickResponse->delete();
        return $quickResponse->fresh();
    }

}
