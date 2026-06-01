<?php

namespace App\Repositories;

use Exception;
use App\Models\BusinessArea;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Traits\VoidClearCache;
use Illuminate\Pagination\LengthAwarePaginator;


class BusinessAreaRepository
{

    use VoidClearCache;


    public function listPaginated(array $opts = []): LengthAwarePaginator
    {
        $limit = $opts['limit'] ?? 20;
        $order = $opts['order'] ?? null;
        $pageNumber = $opts['page'] ?? 1;

        $queryBuilder = BusinessArea::query();
        if ($order) {
            $queryBuilder->orderByRaw($order);
        }
        if ($options['with'] ?? []) {
            $queryBuilder->with($options['with']);
        }
        $result = $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
        return $result;
    }


    public function findOneByName(string $name): ?BusinessArea
    {
        $hash = BusinessArea::buildHash($name);
        $businessArea = BusinessArea::where('hash', $hash)->first();
        return $businessArea;
    }

}
