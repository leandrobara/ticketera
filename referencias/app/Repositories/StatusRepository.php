<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\Status;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Repositories\Traits\VoidClearCache;


class StatusRepository implements Repository
{

    use VoidClearCache;


    public function find(int $id): ?Status
    {
        return Status::find($id);
    }


    public function findOrFail(int $id): Status
    {
        return Status::findOrFail($id);
    }


    public function findOneByStatusIdAndClientId(int $statusId, int $clientId): ?Status
    {
        return Status::where(['id' => $statusId, 'client_id' => $clientId])->first();
    }


    public function findAllByClient(Client $client): Collection
    {
        return Status::where(['client_id' => $client->id])->orderBy('order')->get();
    }


    public function findByClientAndSaleProbability(Client $client, int $saleProbability): Collection
    {
        return Status::where(['client_id' => $client->id, 'sale_probability' => $saleProbability])->get();
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findByClientIdAndIds($client->id, $ids);
    }


    public function findByClientIdAndIds(int $clientId, array $ids): Collection
    {
        return Status::whereIn('id', $ids)->where('client_id', $clientId)->get();
    }


    public function findWithTrashedByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findWithTrashedByClientIdAndIds($client->id, $ids);
    }


    public function findWithTrashedByClientIdAndIds(int $clientId, array $ids): Collection
    {
        return Status::withTrashed()->where('client_id', $clientId)->whereIn('id', $ids)->get();
    }


    public function findMaxOrderByClient(Client $client)
    {
        return Status::where('client_id', $client->id)->max('order');
    }


    public function findOneWithTrashedByClientAndName(Client $client, string $name): ?Status
    {
        $hash = Status::buildHash($name);
        return Status::withTrashed()->where('client_id', $client->id)->where('hash', $hash)->first();
    }


    public function findOneByClientAndName(Client $client, string $name): ?Status
    {
        $hash = Status::buildHash($name);
        return Status::where('client_id', $client->id)->where('hash', $hash)->first();
    }


    public function create(array $data): Status
    {
        $data['hash'] = Status::buildHash($data['name']);
        $status = new Status($data);
        $status->saveOrFail();
        return $status->fresh();
    }


    public function update(Status $status, array $data): Status
    {
        if (isset($data['name'])) {
            $data['hash'] = Status::buildHash($data['name']);
        }
        $status->fill($data)->saveOrFail();
        return $status->fresh();
    }


    public function delete(Status $status): Status
    {
        $status->delete();
        return $status->fresh();
    }

}
