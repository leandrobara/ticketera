<?php

namespace App\Overrides\Dispatchers;

use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Services\API\FailedDispatchedJobService;


class CustomPendingDispatch extends PendingDispatch
{

    public $clientId;

    /**
     * @Override
     */
    public function __destruct()
    {
        try {
            if ($this->afterResponse) {
                app(Dispatcher::class)->dispatchAfterResponse($this->job);
            } else {
                app(Dispatcher::class)->dispatch($this->job);
            }
        } catch (Exception $exception) {
            report($exception);
            $service = resolve(FailedDispatchedJobService::class);
            $service->storeFailedDispatchedJob($this->job, $exception, $this->clientId);
        }
    }

}
