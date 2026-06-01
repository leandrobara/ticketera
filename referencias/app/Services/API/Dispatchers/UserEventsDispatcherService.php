<?php

namespace App\Services\API\Dispatchers;

use App\Models\User;
use Illuminate\Support\Collection;
use App\Services\Traits\CustomDispatch;
use App\Jobs\UserEvents\EnableUserWAPIJob;
use App\Jobs\UserEvents\DisableUserWAPIJob;
use Illuminate\Foundation\Bus\PendingDispatch;


// BORRAR
// @deprecated DEPRECADO
class UserEventsDispatcherService
{

    use CustomDispatch;

    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchEnableUserWAPIJob(User $user, int $delaySecs = 0): void
    {
        $this->doCustomDispatch(EnableUserWAPIJob::class, [$user->id], $delaySecs, $user->client_id);
    }


    public function dispatchDisableUserWAPIJob(User $user, int $delaySecs = 0): void
    {
        $this->doCustomDispatch(DisableUserWAPIJob::class, [$user->id], $delaySecs, $user->client_id);
    }

}
