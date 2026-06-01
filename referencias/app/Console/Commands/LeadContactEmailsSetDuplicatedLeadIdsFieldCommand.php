<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Models\LeadContactEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\LeadContactEmailService;


class LeadContactEmailsSetDuplicatedLeadIdsFieldCommand extends Command
{

    protected $processedIds = [];
    protected $description = 'Fix duplicated lead ids at LeadContactEmails table';
    protected $signature = 'lead-contact-emails:mark-duplicated-lead-ids ' .
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
        $minLeadContactEmailId = (int) ($this->option('min-id') ?? 0);
        $avoidIfRepeatedFieldIsNotNull = (bool) ($this->option('avoid-non-null') ?? false);

        $queryBuilder = Client::query();
        if ($clientId) {
            $queryBuilder->where('id', $clientId);
        }
        if ($minClientId) {
            $queryBuilder->where('id', '>=', $minClientId);
        }
        $clients = $queryBuilder->withTrashed()->orderBy('id', 'asc')->get();


        $repeteadLeadContactEmailsIds = new Collection();
        foreach ($clients as $client) {
            $this->info("\n-----------------------------------");
            $this->info("- Client ID: {$client->id} -> {$client->name}");
            $this->info("-----------------------------------\n");

            $sql = trim("
                SELECT MAX(id) as id, hash, count(*) FROM LeadsContactsEmails 
                WHERE client_id = {$client->id} and deleted_at is null 
                GROUP BY (hash) 
                HAVING COUNT(*) > 1  
                ORDER BY count(*) DESC 
            ");
            $result = DB::select(DB::raw($sql));
            $repeteadLeadContactEmailsIds = collect($result)->pluck('id')->values();

            DB::enableQueryLog();
            $queryBuilder = LeadContactEmail::query();
            if ($minLeadContactEmailId) {
                $queryBuilder->where('id', '>=', $minLeadContactEmailId);
            }
            $queryBuilder = $queryBuilder->whereIn('id', $repeteadLeadContactEmailsIds);
            $queryBuilder = $queryBuilder->select('id', 'email', 'client_id')->orderBy('id', 'asc');
            if ($avoidIfRepeatedFieldIsNotNull) {
                $queryBuilder->whereNull('lead_ids_where_repeated');
            }

            $lastContactEmailId = 0;
            $service = resolve(LeadContactEmailService::class);
            $leadContactEmails = $queryBuilder->where('id', '>', $lastContactEmailId)->take($chunk)->get();

            while ($leadContactEmails->isNotEmpty()) {
                foreach ($leadContactEmails as $leadContactEmail) {
                    if ($this->processedIds[$leadContactEmail->id] ?? null) {
                        continue;
                    }

                    $emailAddr = $leadContactEmail->email;
                    $clientId = $leadContactEmail->client_id;
                    $client = $clients->where('id', $clientId)->first();

                    $updatedLeadContactEmails = $service->updateAndSetRepeteadLeadIdsField(
                        $client, $emailAddr, ['skipUpdateIfSingleResult' => false]
                    );
                    $updatedIds = $updatedLeadContactEmails->pluck('id');
                    foreach ($updatedIds as $id) {
                        $this->processedIds[$id] = true;
                    }

                    $leadIds = $updatedLeadContactEmails->first()->lead_ids_where_repeated;
                    $leadIdsStr = $leadIds ? ('[' . $leadIds->implode(', ') . ']') : 'NULL';
                    $this->info("- ID: {$leadContactEmail->id} -> {$emailAddr}: {$leadIdsStr}");
                }
                $lastContactEmailId = $leadContactEmails->last()->id;
                $leadContactEmails = $queryBuilder->where('id', '>', $lastContactEmailId)->take($chunk)->get();
            }
        }
    }

}
