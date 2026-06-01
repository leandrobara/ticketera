<?php

namespace App\Repositories;

use DB;
use Exception;
use App\Models\Client;
use Illuminate\Support\Collection;
use App\Models\AcquisitionChannel;
use App\Exceptions\DatabaseException;
use App\Repositories\Traits\VoidClearCache;


class AcquisitionChannelRepository implements Repository
{

    use VoidClearCache;


    public function findAllByClient(Client $client): Collection
    {
        return AcquisitionChannel::where('client_id', $client->id)->get();
    }


    public function create(array $data): AcquisitionChannel
    {
        $data['hash'] = AcquisitionChannel::buildHash($data['name']);
        $acquisitionChannel = new AcquisitionChannel($data);
        $acquisitionChannel->saveOrFail();
        return $acquisitionChannel->fresh();
    }


    public function update(AcquisitionChannel $acquisitionChannel, array $data): AcquisitionChannel
    {
        if (isset($data['name'])) {
            $data['hash'] = AcquisitionChannel::buildHash($data['name']);
        }
        $acquisitionChannel->fill($data);
        $acquisitionChannel->saveOrFail();
        return $acquisitionChannel->fresh();
    }


    public function delete(AcquisitionChannel $acquisitionChannel): AcquisitionChannel
    {
        $acquisitionChannel->delete();
        return $acquisitionChannel->fresh();
    }


    public function findOneByClientAndName(Client $client, string $name): ?AcquisitionChannel
    {
        $hash = AcquisitionChannel::buildHash($name);
        $channel = AcquisitionChannel::where('hash', $hash)->where('client_id', $client->id)->first();
        return $channel;
    }


    public function findOneWithTrashedByClientAndName(Client $client, string $name): ?AcquisitionChannel
    {
        $hash = AcquisitionChannel::buildHash($name);
        $channel = AcquisitionChannel::withTrashed()->where('hash', $hash)->where('client_id', $client->id)->first();
        return $channel;
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return AcquisitionChannel::whereIn('id', $ids)->where('client_id', $client->id)->get();
    }

}
