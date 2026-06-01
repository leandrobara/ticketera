<?php

namespace App\Services\API;

use App\Models\UserCustomFilter;
use App\Repositories\Repository;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\UserCustomFilter\UserCustomFilterDTO;

class UserCustomFilterService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $userCustomFilterRepository;


    public function __construct(Repository $userCustomFilterRepository)
    {
        $this->userCustomFilterRepository = $userCustomFilterRepository;
    }


    public function findAllByUser()
    {
        return $this->userCustomFilterRepository->findAllByUserAndClient($this->getUser(), $this->getClient());
    }


    public function create(UserCustomFilterDTO $dto)
    {
        return $this->userCustomFilterRepository->create($dto);
    }


    public function update(UserCustomFilter $userCustomFilter, UserCustomFilterDTO $dto)
    {
        return $this->userCustomFilterRepository->update($userCustomFilter, $dto);
    }


    public function delete(UserCustomFilter $userCustomFilter)
    {
        return $this->userCustomFilterRepository->delete($userCustomFilter);
    }

}
