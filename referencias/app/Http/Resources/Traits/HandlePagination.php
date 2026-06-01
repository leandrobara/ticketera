<?php

namespace App\Http\Resources\Traits;

use Illuminate\Pagination\LengthAwarePaginator;

trait HandlePagination
{

    public function addPaginationInfo($resource, array $response): array
    {
        if ($resource instanceof LengthAwarePaginator) {
            $newResponse = [];
            $newResponse['data'] = $response;
            $newResponse['pagination'] = [
                'total' => $resource->total(),
                'lastPage' => $resource->lastPage(),
                'perPage' => $resource->perPage(),
                'currentPage' => $resource->currentPage(),
                'nextPageUrl' => $resource->nextPageUrl(),
                'previousPageUrl' => $resource->previousPageUrl(),
            ];

            return $newResponse;
        }

        return $response;
    }
}
