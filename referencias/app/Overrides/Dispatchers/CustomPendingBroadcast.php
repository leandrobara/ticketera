<?php

namespace App\Overrides\Dispatchers;

use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Broadcasting\PendingBroadcast;
use App\Services\API\FailedDispatchedJobService;


class CustomPendingBroadcast extends PendingBroadcast
{

    public $clientId;

    /**
     * @Override
     */
    public function __destruct()
    {
        try {
            $this->events->dispatch($this->event);
        } catch (Exception $exception) {
            report($exception);
            $service = resolve(FailedDispatchedJobService::class);
            $service->storeFailedDispatchedJob($this->event, $exception, $this->clientId);
        }
    }

}
