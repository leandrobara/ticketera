<?php

namespace App\Jobs\LeadEvents;

use DateTime;
use Throwable;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use App\Models\LeadContactPhone;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\LeadContactPhoneService;
use App\Overrides\Dispatchers\CustomDispatchable;


class LeadDuplicatedPhoneManagementJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $client;
    public $action;
    public $newPhoneNumber;
    public $previousPhoneNumber;
    

    public function __construct(
        int $clientId,
        string $action,
        string $newPhoneNumber,
        ?string $previousPhoneNumber = null
    ) {
        $this->action = $action;
        $this->newPhoneNumber = $newPhoneNumber;
        $this->client = Client::findOrFail($clientId);
        $this->previousPhoneNumber = $previousPhoneNumber;
    }


    public function handle()
    {
        $client = $this->client;
        $service = resolve(LeadContactPhoneService::class);
        $newPhone = trim(strtolower($this->newPhoneNumber));
        $prevPhone = trim(strtolower($this->previousPhoneNumber));

        if ($this->action == 'update') {
            $service->updateAndSetRepeteadLeadIdsField($client, $prevPhone, ['skipUpdateIfSingleResult' => false]);
            $service->updateAndSetRepeteadLeadIdsField($client, $newPhone, ['skipUpdateIfSingleResult' => false]);
        }

        if ($this->action == 'create') {
            $service->updateAndSetRepeteadLeadIdsField($client, $newPhone, ['skipUpdateIfSingleResult' => true]);
        }

        if ($this->action == 'delete') {
            $service->updateAndSetRepeteadLeadIdsField($client, $newPhone, ['skipUpdateIfSingleResult' => false]);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }
}
