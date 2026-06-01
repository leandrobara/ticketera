<?php

namespace App\Repositories;

use Exception;
use App\Models\User;
use App\Models\FailedDispatchedJob;
use Illuminate\Support\Collection;


class FailedDispatchedJobRepository implements Repository
{

    public function find(int $id): ?FailedDispatchedJob
    {
        return FailedDispatchedJob::find($id);
    }

    public function create(array $data): FailedDispatchedJob
    {
        $userToken = new FailedDispatchedJob($data);
        $userToken->saveOrFail();
        return $userToken->fresh();
    }


    public function update(FailedDispatchedJob $userToken, array $data): FailedDispatchedJob
    {
        $userToken->fill($data)->saveOrFail();
        return $userToken->fresh();
    }


    public function delete(FailedDispatchedJob $userToken): FailedDispatchedJob
    {
        $userToken->delete();
        return $userToken->fresh();
    }

}
