<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Models\LeadContactPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\LeadContactPhoneService;


class LeadContactPhonesSetDuplicatedLeadIdsFieldCommand extends Command
{

    protected $processedIds = [];
    protected $description = 'Fix duplicated lead ids at LeadContactPhones table';
    protected $signature = 'lead-contact-phones:mark-duplicated-lead-ids ' .
        '{--chunk=} {--min-id=} {--min-client-id=} {--client-id=} {--avoid-non-null=}'
    ;


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $chunk = (int) ($this->option('chunk') ?? 5000);
        $clientId = (int) ($this->option('client-id') ?? 0);
        $minClientId = (int) ($this->option('min-client-id') ?? 0);
        $minLeadContactPhoneId = (int) ($this->option('min-id') ?? 0);
        $avoidIfRepeatedFieldIsNotNull = (bool) ($this->option('avoid-non-null') ?? false);

        $queryBuilder = Client::query();
        if ($clientId) {
            $queryBuilder->where('id', $clientId);
        }
        if ($minClientId) {
            $queryBuilder->where('id', '>=', $minClientId);
        }
        $clients = $queryBuilder->withTrashed()->orderBy('id', 'asc')->get();


        $repeteadLeadContactPhoneIds = new Collection();
        foreach ($clients as $client) {
            $this->processedIds = [];

            $this->info("\n-----------------------------------");
            $this->info("- Client ID: {$client->id} -> {$client->name}");
            $this->info("-----------------------------------\n");

            $sql = trim("
                SELECT MAX(id) as id, hash, count(*) FROM LeadsContactsPhones 
                WHERE client_id = {$client->id} and deleted_at is null 
                GROUP BY (hash) 
                HAVING COUNT(*) > 1  
                ORDER BY count(*) DESC 
            ");
            $result = DB::select(DB::raw($sql));
            $repeteadLeadContactPhoneIds = collect($result)->pluck('id')->values();
            $repeteadLeadContactPhoneIdsChunks = $repeteadLeadContactPhoneIds->chunk(1000);

            foreach ($repeteadLeadContactPhoneIdsChunks as $repeteadLeadContactPhoneIdsChunk) {
                $queryBuilder = LeadContactPhone::query();
                if ($minLeadContactPhoneId) {
                    $queryBuilder->where('id', '>=', $minLeadContactPhoneId);
                }
                $queryBuilder = $queryBuilder->whereIn('id', $repeteadLeadContactPhoneIdsChunk);
                $queryBuilder = $queryBuilder->select('id', 'phone', 'client_id')->orderBy('id', 'asc');
                if ($avoidIfRepeatedFieldIsNotNull) {
                    $queryBuilder->whereNull('lead_ids_where_repeated');
                }

                $lastContactPhoneId = 0;
                $service = resolve(LeadContactPhoneService::class);
                $leadContactPhones = $queryBuilder->where('id', '>', $lastContactPhoneId)->take($chunk)->get();

                while ($leadContactPhones->isNotEmpty()) {
                    foreach ($leadContactPhones as $leadContactPhone) {
                        if ($this->processedIds[$leadContactPhone->id] ?? null) {
                            continue;
                        }

                        $phoneAddr = $leadContactPhone->phone;
                        $clientId = $leadContactPhone->client_id;
                        $client = $clients->where('id', $clientId)->first();

                        $updatedLeadContactPhones = $service->updateAndSetRepeteadLeadIdsField(
                            $client, $phoneAddr, ['skipUpdateIfSingleResult' => false]
                        );
                        if ($updatedLeadContactPhones->isEmpty()) {
                            continue;
                        }
                        $updatedIds = $updatedLeadContactPhones->pluck('id');
                        foreach ($updatedIds as $id) {
                            $this->processedIds[$id] = true;
                        }

                        $leadIds = $updatedLeadContactPhones->first()->lead_ids_where_repeated;
                        $leadIdsStr = $leadIds ? ('[' . $leadIds->implode(', ') . ']') : 'NULL';
                        $this->info("- ID: {$leadContactPhone->id} -> {$phoneAddr}: {$leadIdsStr}");
                    }
                    $lastContactPhoneId = $leadContactPhones->last()->id;
                    $leadContactPhones = $queryBuilder->where('id', '>', $lastContactPhoneId)->take($chunk)->get();
                }
            }
        }
    }

}
