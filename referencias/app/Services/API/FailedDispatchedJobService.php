<?php

namespace App\Services\API;

use Exception;
use App\Models\FailedDispatchedJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\FailedDispatchedJobRepository;


class FailedDispatchedJobService
{

    private $failedDispatchedJobRepository;


    public function __construct(FailedDispatchedJobRepository $failedDispatchedJobRepository)
    {
        $this->failedDispatchedJobRepository = $failedDispatchedJobRepository;
    }


    /**
     * @param ShouldQueue|ShouldBroacdst $job
     */
    public function storeFailedDispatchedJob($job, Exception $e, ?int $clientId): FailedDispatchedJob
    {
        $queueName = $job->queue;
        $serializedJob = serialize($job);
        $exceptionString = (string) $e;
        $data = [
            'queue' => $queueName,
            'client_id' => $clientId,
            'exception' => $exceptionString,
            'serialized_job' => $serializedJob,
        ];
        return $this->create($data);
    }


    public function create(array $data): FailedDispatchedJob
    {
        $model = $this->failedDispatchedJobRepository->create($data);
        return $model;
    }

}

