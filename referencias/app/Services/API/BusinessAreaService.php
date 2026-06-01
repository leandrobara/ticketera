<?php

namespace App\Services\API;

use Exception;
use App\Models\Client;
use App\Models\BusinessArea;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Repositories\BusinessAreaRepository;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;


class BusinessAreaService
{

    use GetClientFromRequest;


    public function __construct(
        private BusinessAreaRepository $businessAreaRepository
    ) {
    }


    public function list(array $opts = []): LengthAwarePaginator
    {
        $repoOpts = [
            'page' => $opts['page'] ?? 1,
            'with' => $opts['with'] ?? [],
            'limit' => $opts['limit'] ?? 99999,
        ];
        $response = $this->businessAreaRepository->listPaginated($repoOpts);
        return $response;
    }


    public function findOneByName(string $name): ?BusinessArea
    {
        return $this->businessAreaRepository->findOneByName($name);
    }

}
