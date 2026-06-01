<?php

namespace App\Repositories;

use App\Models\Manager;


class ManagerRepository
{

    public function findOneByName(string $name): ?Manager
    {
        $name = strtolower(trim($name));
        return Manager::whereRaw('LOWER(name) = ?', [$name])->first();
    }


    public function create(array $data): Manager
    {
        $manager = new Manager($data);
        $manager->saveOrFail();
        return $manager->fresh();
    }


    public function update(Manager $manager, array $data): Manager
    {
        $manager->fill($data)->saveOrFail();
        return $manager->fresh();
    }


    public function delete(Manager $manager): Manager
    {
        $manager->delete();
        return $manager->fresh();
    }

}
