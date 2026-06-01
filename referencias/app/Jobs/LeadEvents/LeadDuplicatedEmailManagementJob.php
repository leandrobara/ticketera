<?php

namespace App\Jobs\LeadEvents;

use DateTime;
use Throwable;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use App\Models\LeadContactEmail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\LeadContactEmailService;
use App\Overrides\Dispatchers\CustomDispatchable;


class LeadDuplicatedEmailManagementJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $client;
    public $action;
    public $newEmailAddr;
    public $previousEmailAddr;
    

    public function __construct(int $clientId, string $action, string $newEmailAddr, ?string $previousEmailAddr = null)
    {
        $this->action = $action;
        $this->newEmailAddr = $newEmailAddr;
        $this->client = Client::findOrFail($clientId);
        $this->previousEmailAddr = $previousEmailAddr;
    }


    public function handle()
    {
        $service = resolve(LeadContactEmailService::class);
        $client = $this->client;
        $newEmailAddr = trim(strtolower($this->newEmailAddr));
        $prevEmailAddr = trim(strtolower($this->previousEmailAddr));

        if ($this->action == 'update') {
            // No rotar estos dos!
            $service->updateAndSetRepeteadLeadIdsField($client, $prevEmailAddr, ['skipUpdateIfSingleResult' => false]);
            $service->updateAndSetRepeteadLeadIdsField($client, $newEmailAddr, ['skipUpdateIfSingleResult' => false]);
        }

        if ($this->action == 'create') {
            $service->updateAndSetRepeteadLeadIdsField($client, $newEmailAddr, ['skipUpdateIfSingleResult' => true]);
        }

        if ($this->action == 'delete') {
            $service->updateAndSetRepeteadLeadIdsField($client, $newEmailAddr, ['skipUpdateIfSingleResult' => false]);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }
}
