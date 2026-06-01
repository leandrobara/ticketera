<?php

namespace App\Services\API;

use Exception;
use Throwable;
use App\Models\Client;
use App\Models\Status;
use App\Services\Traits\Sortable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\StatusRepository;
use App\Repositories\Cache\StatusRepositoryCache;


class StatusService
{

    use GetClientFromRequest, Sortable;


    public function __construct(
        protected readonly StatusCategoryService $statusCategoryService,
        protected readonly StatusRepository | StatusRepositoryCache $statusRepository,
    ) {
    }


    public function find(int $id): ?Status
    {
        return $this->statusRepository->find($id);
    }


    public function findOneByClientAndName(Client $client, string $name): ?Status
    {
        return $this->statusRepository->findOneByClientAndName($client, $name);
    }


    public function findOneByStatusIdAndClientId(int $statusId, int $clientId): ?Status
    {
        return $this->statusRepository->findOneByStatusIdAndClientId($statusId, $clientId);
    }


    public function findOneByClientAndId(Client $client, int $statusId): ?Status
    {
        return $this->findOneByStatusIdAndClientId($statusId, $client->id);
    }


    public function findAll(): Collection
    {
        return $this->statusRepository->findAllByClient($this->getClient());
    }


    public function findAllByClient(Client $client)
    {
        return $this->statusRepository->findAllByClient($client);
    }


    public function findBySaleProbability($saleProbability)
    {
        return $this->statusRepository->findByClientAndSaleProbability($this->getClient(), $saleProbability);
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findByClientIdAndIds($client->id, $ids);
    }


    public function findByClientIdAndIds(int $clientId, array $ids): Collection
    {
        return $this->statusRepository->findByClientIdAndIds($clientId, $ids);
    }


    public function findWithTrashedByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findWithTrashedByClientIdAndIds($client->id, $ids);
    }


    public function findWithTrashedByClientIdAndIds(int $clientId, array $ids): Collection
    {
        return $this->statusRepository->findWithTrashedByClientIdAndIds($clientId, $ids);
    }


    public function getLeadsCount(Status $status)
    {
        return $status->leadsCount;
    }


    public function findOneWithTrashedByClientAndName(Client $client, string $name): ?Status
    {
        return $this->statusRepository->findOneWithTrashedByClientAndName($client, $name);
    }


    public function create($data)
    {
        $client = $data['client'] ?? $this->getClient();
        unset($data['client']);

        $status = $this->statusRepository->findOneWithTrashedByClientAndName($client, $data['name']);

        if ($status && $status->deleted_at) {
            $status->deleted_at = null;
            $status->fill($data);
            $status->save();
            $this->statusRepository->clearCacheForClient($client->id);
            return $status;
        }

        $data['client_id'] = $client->id;
        $lastOrder = $this->statusRepository->findMaxOrderByClient($client);
        $data['order'] = $lastOrder == null ? 0 : $lastOrder + 1;
        $newStatus = $this->statusRepository->create($data);

        return $newStatus;
    }


    public function update(Status $status, array $data)
    {
        return $this->statusRepository->update($status, $data);
    }


    public function delete(Status $status)
    {
        try {
            DB::beginTransaction();
            $status = $this->statusRepository->delete($status);
            $statuses = $this->statusRepository->findAllByClient($this->getClient());
            $this->reOrder($statuses);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    
        return $status;
    }


    public function findOrCreateStatus(Client $client, $name, $category = null)
    {
        $status = $this->statusRepository->findOneByClientAndName($client, $name);
        if ($status) {
            return $status;
        }
        $data = ['name' => $name, 'client' => $client];
        if ($category) {
            $data['category'] = $category;
        }
        $status = $this->create($data);
        return $status;
    }


    public function findOrCreateStatusNew(Client $client): Status
    {
        $name = 'Nuevo';
        $status = $this->statusRepository->findOneByClientAndName($client, $name);
        if ($status) {
            return $status;
        }
        $status = $this->create([
            'name' => $name,
            'category' => 'new',
            'client' => $client,
        ]);
        return $status;
    }


    // Evito transactions, por que esto ya se llama en una transaction más general.
    public function createNewClientDefaults(Client $client): Collection
    {
        $defaultStatusCategories = $this->statusCategoryService->createNewClientDefaults($client);
        $statusNames = [
            'Nuevo', 'Contactado', 'En seguimiento', 'Últimos detalles', 'Venta ganada', 'Sin venta', 'Irrelevante'
        ];

        $statusList = collect([]);
        foreach ($statusNames as $statusName) {
            $attrs = [
                'name' => $statusName,
                'client_id' => $client->id,
            ];
            $status = Status::factory()->state($attrs)->newClientDefault($defaultStatusCategories)->make($attrs);
            $status->saveOrFail();
            $status = $status->fresh();
            $statusList->push($status);
        }
        return $statusList;
    }

}