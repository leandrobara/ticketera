<?php

namespace App\Services\API;


use App\Models\Manager;
use App\Repositories\ManagerRepository;
use App\Services\Traits\GetClientFromRequest;


class ManagerService
{

    private $managerRepository;


    public function __construct(ManagerRepository $managerRepository)
    {
        $this->managerRepository = $managerRepository;
    }


    public function createOrUpdate(array $data): Manager
    {
        $name = $data['name'];
        $manager = $this->findOneByName($name);
        if ($manager) {
            return $this->update($manager, $data);
        }

        return $this->create($data);
    }


    public function create(array $data): Manager
    {
        return $this->managerRepository->create($data);
    }


    public function update(Manager $manager, array $data): Manager
    {
        return $this->managerRepository->update($manager, $data);
    }

    public function findOneByName(string $name): ?Manager
    {
        return $this->managerRepository->findOneByName($name);
    }


}
