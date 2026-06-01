<?php

namespace App\Services\API;

use Exception;
use App\Models\Client;
use App\Models\StatusCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\StatusCategoryRepository;


class StatusCategoryService
{

    use GetClientFromRequest;

    
    public function __construct(
        private readonly StatusCategoryRepository $statusCategoryRepository
    ) {
    }


    public function find(int $id): ?StatusCategory
    {
        return $this->statusCategoryRepository->find($id);
    }


    public function findAllByClient(?Client $client = null)
    {
        $client = (!$client) ? $this->getClient() : $client;
        return $this->statusCategoryRepository->findAllByClient($client);
    }


    public function getStatusRelatedCount(StatusCategory $statusCategory)
    {
        return $statusCategory->statusCount;
    }


    public function changeOrder(StatusCategory $statusCategory, string $direction)
    {
        if (!in_array($direction, ['up', 'down'])) {
            throw new Exception('Direction is not allowed');
        }
        if ($statusCategory->is_irrelevant) {
            throw new Exception('Irrelevant status category order can not change');
        }

        $oldOrder = $statusCategory->order;
        $newOrder = ($direction == 'up') ? $oldOrder - 1 : $oldOrder + 1;
        
        $statusCategories = $this->statusCategoryRepository->findAllByClient($this->getClient());
        $statusCategoryToUpdate = $statusCategories->filter(function ($statusCateg) use ($newOrder) {
            return $statusCateg->order == $newOrder;
        })->first();

        try {
            DB::beginTransaction();
            $this->update($statusCategory, ['order' => $newOrder]);
            $this->update($statusCategoryToUpdate, ['order' => $oldOrder]);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $statusCategory;
    }


    public function createNewClientDefaults(Client $client): Collection
    {
        $statusCategories = [
            ['name' => 'Nuevo', 'sale_probability' => 10, 'order' => 0, 'is_irrelevant' => false],
            ['name' => 'En proceso', 'sale_probability' => 25, 'order' => 1, 'is_irrelevant' => false],
            ['name' => 'Sin venta', 'sale_probability' => 0, 'order' => 2, 'is_irrelevant' => false],
            ['name' => 'Con venta', 'sale_probability' => 100, 'order' => 3, 'is_irrelevant' => false],
            ['name' => 'Irrelevante', 'sale_probability' => 0, 'order' => 99, 'is_irrelevant' => true],
        ];

        $statusCategoryList = collect([]);
        foreach ($statusCategories as $statusCategory) {
            $hash = StatusCategory::buildHash($statusCategory['name']);
            $attrs = $statusCategory + ['hash' => $hash, 'client_id' => $client->id];
            $statusCategory = new StatusCategory($attrs);
            $statusCategory->saveOrFail();
            $statusCategoryList->push($statusCategory->fresh());
        }
        return $statusCategoryList;
    }


    public function create($data)
    {
        $client = $data['client'] ?? $this->getClient();
        $data['client_id'] = $client->id;
        unset($data['client']);

        $lastOrder = $this->statusCategoryRepository->findMaxOrderByClient($client);
        $data['order'] = $lastOrder === null ? 0 : $lastOrder + 1;

        return $this->statusCategoryRepository->create($data);
    }


    public function update(StatusCategory $statusCategory, array $data)
    {
        return $this->statusCategoryRepository->update($statusCategory, $data);
    }


    public function delete(StatusCategory $statusCategory)
    {
        try {
            DB::beginTransaction();
            $statusCategory = $this->statusCategoryRepository->delete($statusCategory);
            $statusCategories = $this->statusCategoryRepository->findAllByClient($this->getClient());
            $nonIrrelevantStatusCategories = $statusCategories->filter(fn ($sc) => !$sc->is_irrelevant);
            $this->reOrderAll($this->getClient());
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $statusCategory;
    }


    protected function reOrderAll(Client $client): void
    {
        $order = 0;
        $statusCategories = $this->statusCategoryRepository->findAllByClient($client);
        foreach ($statusCategories as $statusCategory) {
            if ($statusCategory->is_irrelevant) {
                continue;
            }
            $statusCategory->order = $order;
            $statusCategory->saveOrFail();
            $order++;
        }
    }

}
