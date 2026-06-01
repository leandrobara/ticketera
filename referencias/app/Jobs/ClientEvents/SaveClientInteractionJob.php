<?php

namespace App\Jobs\ClientEvents;

use Throwable;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use App\Models\ClientInteraction;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\ClientEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\ClientInteractionService;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveClientInteractionJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    
    public $clientId;
    

    public function __construct(int $clientId)
    {
        $this->clientId = $clientId;
    }


    public function handle()
    {
        $rand = mt_rand(1, 300000);
        usleep($rand);
        $key = 'SaveClientInteractionJob:handle:client_id:' . $this->clientId;
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($key, 1);
        if (!$lockIsGranted) {
            return null;
        }

        $client = Client::findOrFail($this->clientId);
        resolve(ClientInteractionService::class)->countNewInteraction($client);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
