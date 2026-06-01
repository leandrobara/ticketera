<?php

namespace App\Services\Traits;

use App\Overrides\Dispatchers\CustomPendingDispatch;


trait CustomDispatch
{
    
    protected function doCustomDispatch(
        string $jobClassName,
        array $params = [],
        int | float | null $delaySecs = null,
        ?int $clientId = null,
        ?string $eventQueueName = null,
    ): CustomPendingDispatch {
        $eventQueueName = $eventQueueName ?? $this->eventQueueName;
        // App\Overrides\Dispatchers\CustomPendingDispatch
        $customPendingDispatch = call_user_func("{$jobClassName}::dispatch", ...$params);
        $customPendingDispatch->onQueue($eventQueueName)->onConnection($this->queueConnection);

        if ($delaySecs !== null) {
            $delayTime = now()->addSeconds($delaySecs);
            $customPendingDispatch->delay($delayTime);
        }

        $customPendingDispatch->clientId = $clientId;
        return $customPendingDispatch;
    }

}
