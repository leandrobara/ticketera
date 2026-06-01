<?php

namespace App\Console\Commands;

use DateTime;
use Exception;
use App\Models\Lead;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Services\API\LeadService;
use App\Helpers\FacebookAdHelper;
use App\Models\ClientFacebookPage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\FacebookLogService;
use App\DTO\FacebookPage\ClientFacebookPageLeadInfoDTO;
use App\Exceptions\Services\LeadService\ExistentLeadException;


class FacebookRetrieveLeadsCommand extends Command
{

    public string $logUuid;

    protected $description = 'Retrieve leads from clients Facebook pages';
    protected $signature = 'facebook:retrieve-leads {--client-id=} {--facebook-page-id=} {--avoid-lock=}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clientId = (int) ($this->option('client-id') ?? 0);
        $this->logUuid = Str::afterLast(Str::orderedUuid(), '-');
        $avoidLock = (bool) ($this->option('avoid-lock') ?? false);
        $facebookPageId = (int) ($this->option('facebook-page-id') ?? 0);

        $lockKey = 'FacebookRetrieveLeadsCommand';
        if (!$avoidLock && !resolve(LockHelper::class)->getLockByName($lockKey, 600)) {
            die('Locked' .  PHP_EOL);
        }

        $leadService = resolve(LeadService::class);
        $facebookAdHelper = resolve(FacebookAdHelper::class);
        $facebookLogService = resolve(FacebookLogService::class);

        $queryBuilder = Client::query()->where('enabled', true);
        if ($clientId) {
            $queryBuilder->where('id', $clientId);
        }
        $clients = $queryBuilder->orderBy('id')->get();

        foreach ($clients as $client) {
            try {
                $this->logInfo("------------------------------------");
                $this->logInfo("- Client '{$client->name}' (ID: {$client->id})");

                $queryBuilder = ClientFacebookPage::query()->where('client_id', $client->id);
                if ($facebookPageId) {
                    $queryBuilder->where('id', $facebookPageId);
                }
                $clientFacebookPages = $queryBuilder->orderBy('id')->get();
                $this->logInfo("- ClientFacebookPages count: {$clientFacebookPages->count()}");

                foreach ($clientFacebookPages as $clientFacebookPage) {
                    $this->logInfo(
                        "- clientFacebookPage '{$clientFacebookPage->name}' (ID: {$clientFacebookPage->id})"
                    );

                    $opts = [
                        'limit' => 8,
                        'includeFbFormData' => true,
                        'enableCrashReporter' => false,
                        'dateEnd' => new DateTime('now'),
                        'dateStart' => new DateTime('yesterday'),
                    ];
                    $fbLeads = $facebookAdHelper->getRecentLeads($clientFacebookPage, $opts);
                    $this->logInfo("- fbLeads count: {$fbLeads->count()}");

                    $fbLeadDTOs = new Collection();
                    foreach ($fbLeads as $fbLeadDataArr) {
                        $fbLeadId = $fbLeadDataArr['id'];

                        $existentFbLog = $facebookLogService->findOneByFBLeadId($fbLeadId);
                        if ($existentFbLog) {
                            $this->logInfo("- FacebookLog with ID '{$fbLeadId}' ALREADY EXISTS in MongoDB");
                            $this->logInfo("----");
                            continue;
                        }
                        $this->logInfo("- FacebookLog with ID '{$fbLeadId}' DOES NOT EXISTS in MongoDB");
                        $this->logInfo("- Creating new lead...");

                        $fbFormDataArr = [];
                        $adsInfoArr = [];
                        if (isset($fbLeadDataArr['fbFormData'])) {
                            $fbFormDataArr = $fbLeadDataArr['fbFormData'];
                            unset($fbLeadDataArr['fbFormData']);
                        }
                        if (!empty($fbLeadDataArr['ad_id'])) {
                            try {
                                $adsInfoArr = $facebookAdHelper->getCampaignAndAdsNamesByAdId(
                                    $fbLeadDataArr['ad_id'], $clientFacebookPage
                                );
                            } catch (Exception $e) {
                                report($e);
                            }
                        }
                        $fbLeadDTO = ClientFacebookPageLeadInfoDTO::build(
                            $clientFacebookPage, $fbLeadDataArr, $fbFormDataArr, $adsInfoArr
                        );
                        try {
                            $lead = $leadService->createFromFacebookLeadDTO($fbLeadDTO);
                        } catch (ExistentLeadException $e) {
                            $lead = $e->getLead();
                            $this->logInfo("- [ExistentLeadException] Lead ALREADY EXISTS with ID: {$lead->id}");
                            $this->logInfo("----");
                            continue;
                        }

                        $this->logInfo("- Lead '{$lead->id}' CREATED");
                        $this->logInfo("----");
                        $facebookLogService->saveLeadData($clientFacebookPage, $fbLeadDataArr, $fbFormDataArr);

                        resolve(LockHelper::class)->getLockByName($lockKey, 300);
                    }
                }
                $this->logInfo("------------------------------------\n\n");
            } catch (Exception $e) {
                $this->logInfo("- [EXCEPTION ERROR] {$e->getMessage()}");
                $this->logInfo("------------------------------------\n\n");
                continue;
            }
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    protected function logInfo(string $msg, bool $printConsoleInfo = true): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
        if ($printConsoleInfo) {
            $this->info($msg);
        }
    }


    private function getInfoLog()
    {
        return Log::channel('facebook_retrieve_leads_command_info');
    }

}
