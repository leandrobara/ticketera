<?php

namespace App\Jobs\ClientEvents;

use Throwable;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\ClientEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\ClientUsageLogService;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveVisitedScreenUrlUsageLogJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels;
    

    public function __construct(
        protected readonly int $clientId,
        protected readonly int $userId,
        protected readonly string $visitedScreenUrl,
    ) {
    }


    public function handle()
    {
        resolve(ClientUsageLogService::class)->storeVisitedScreenUrl(
            $this->clientId, $this->userId, $this->visitedScreenUrl
        );
    }

}
