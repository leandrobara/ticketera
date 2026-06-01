<?php

namespace App\Repositories\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

trait ChunkedQueries
{

    public function chunkQuery(
        Builder $originalQueryBuilder,
        Collection $collectionToChunk,
        string $chunkField,
        int $chunkSize = 200
    ): Collection {
        $results = new Collection();
        $chunks = $collectionToChunk->chunk($chunkSize);
        foreach ($chunks as $chunkPartArr) {
            $queryBuilder = clone $originalQueryBuilder;
            $partialResults = $queryBuilder->whereIn($chunkField, $chunkPartArr)->get();
            $results = $results->merge($partialResults);
        }
        return $results;
    }

}
