<?php

namespace App\Jobs\LeadEvents;

use DateTime;
use Exception;
use Throwable;
use App\Models\Lead;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Services\API\StatusService;
use Illuminate\Support\Facades\Log;
use App\Services\API\LeadSaleService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;


class LeadStatusMassiveChangedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $leadId;
    public $statusId;
    public $clientId;
    public $logUuid = null;
    

    public function __construct(int $leadId, int $statusId, int $userId, int $clientId)
    {
        $this->userId = $userId;
        $this->leadId = $leadId;
        $this->statusId = $statusId;
        $this->clientId = $clientId;
    }


    // Por ahora lo único que hace es crear o no leadSale
    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $this->logInfo('Starting LeadStatusMassiveChangedJob');
        $this->logInfo("clientId: {$this->clientId}");

        $status = resolve(StatusService::class)->findOneByStatusIdAndClientId($this->statusId, $this->clientId);
        if (!$status) {
            throw new Exception('Status does not exist');
        }
        $this->logInfo("statusId: {$status->id}");
        $this->logInfo("status.sale_probability: {$status->sale_probability}");
        if ($status->sale_probability !== 100) {
            $this->logInfo('status.sale_probability is not 100. RETURNING');
            return true;
        }

        $user = resolve(UserService::class)->findOneByUserIdAndClientId($this->userId, $this->clientId);
        if (!$user) {
            throw new Exception('User does not exist');
        }
        $this->logInfo("userId: {$user->id}");
        
        $clientSettings = $user->client->clientSettings;
        $this->logInfo("clientId: {$user->client->id}");
        $this->logInfo("clientSettingsId: {$user->client->clientSettings->id}");
        $this->logInfo("clientSettings.register_sales_info: {$clientSettings->register_sales_info}");

        if ($user->client->id != $this->clientId) {
            throw new Exception('clientId does not match with user.client.id');
        }

        if (!$clientSettings->register_sales_info) {
            $this->logInfo('clientSettings.register_sales_info is false. RETURNING');
            return true;
        }

        $lead = Lead::findOrFail($this->leadId);
        $this->logInfo("leadId: {$lead->id}");
        
        // Es redundante, pero lo dejo para alivianar carga cognitiva.
        if ($status->sale_probability === 100 && $clientSettings->register_sales_info) {
            $this->logInfo('Creating new LeadSale');
            $leadSaleData = [
                'amount' => 0,
                'description' => 0,
                'user_id' => $user->id,
                'sale_date' => new DateTime(),
                'is_manually_created' => false,
                'client_id' => $lead->client->id,
            ];
            resolve(LeadSaleService::class)->create($lead, $leadSaleData);
        }

        $this->logInfo('Finished');
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('LeadStatusMassiveChangedJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('LeadStatusMassiveChangedJobInfo');
    }

}
