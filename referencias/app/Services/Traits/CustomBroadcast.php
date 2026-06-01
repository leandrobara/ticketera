<?php

namespace App\Services\Traits;


use App\Overrides\Dispatchers\CustomPendingBroadcast;


trait CustomBroadcast
{
    
    protected function doCustomBroadcast($event = null, ?int $clientId = null): CustomPendingBroadcast
    {
        // App\Overrides\Dispatchers\CustomPendingBroadcast
        $customPendingBroadcast = new CustomPendingBroadcast(app()->make('events'), $event);
        $customPendingBroadcast->clientId = $clientId;
        return $customPendingBroadcast;
    }

}
