<?php

namespace App\Console\Commands;
use DateTime;
use DateTimeZone;
use App\Models\Lead;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Services\API\LeadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\API\EventsLogService;
use Illuminate\Support\Facades\Artisan;


class SetLeadsLastStatusChangedAtCommand extends Command
{

    protected $processedIds = [];
    protected $description = 'Set last_status_changed_at by last status change date';
    protected $signature = 'set-last-status-changed-at ' .
        '{--chunk=} {--min-lead-id=} {--min-client-id=} {--client-id=} {--avoid-filled=}'
    ;


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        ini_set('memory_limit', '256M');

        $leadService = resolve(LeadService::class);
        $eventsLogService = resolve(EventsLogService::class);

        $chunk = (int) ($this->option('chunk') ?? 100);
        $clientId = (int) ($this->option('client-id') ?? 0);
        $minLeadId = (int) ($this->option('min-lead-id') ?? 0);
        $minClientId = (int) ($this->option('min-client-id') ?? 0);
        $avoidFilled = (bool) ($this->option('avoid-filled') ?? true);

        $queryBuilder = Client::query();
        if ($clientId) {
            $queryBuilder->where('id', $clientId);
        }
        if ($minClientId) {
            $queryBuilder->where('id', '>=', $minClientId);
        }
        $clients = $queryBuilder->withTrashed()->orderBy('id', 'asc')->get();

        foreach ($clients as $client) {
            $this->info("\n-----------------------------------");
            $this->info("- Client ID: {$client->id} -> {$client->name}");
            $this->info("-----------------------------------\n");

            $queryBuilder = Lead::query();
            $queryBuilder = $queryBuilder->select('id', 'client_id', 'created_at')->orderBy('id', 'asc');
            $queryBuilder = $queryBuilder->where('client_id', $client->id);
        
            if ($minLeadId) {
                $queryBuilder = $queryBuilder->where('id', '>=', $minLeadId);
            }
            if ($avoidFilled) {
                $queryBuilder = $queryBuilder->whereNull('last_status_changed_at');
            }

            $lastLeadId = 0;
            $leads = $queryBuilder->limit($chunk)->get();
            while ($leads->isNotEmpty()) {
                $events = $eventsLogService->findEventsFromManyLeads(
                    $leads, ['lead_created', 'lead_manually_created', 'lead_status_updated']
                );
                $eventsGroupedByLead = collect($events)->sortBy('createdAtTs')->groupBy('log.lead.id');

                foreach ($leads as $lead) {
                    $leadEvents = $eventsGroupedByLead->get($lead->id) ?? null;

                    $lastStatusChangedAt = $lead->created_at->toDateTime();
                    $lead->last_status_changed_at = $lastStatusChangedAt;

                    if ($leadEvents) {
                        $lastEvent = $leadEvents->last();
                        $createdAtTs = $lastEvent['createdAtTs'];
                        $lastStatusChangedAt = new DateTime("@{$createdAtTs}");
                        $lead->last_status_changed_at = $lastStatusChangedAt;
                    }

                    $lead->saveOrFail();

                    $this->info("\n-----------------------------------");
                    $this->info("- Client ID: {$client->id}");
                    $this->info("- Lead ID: {$lead->id}");
                    $this->info("- lastStatusChangedAt: {$lastStatusChangedAt->format('Y-m-d H:i:s')}");
                    $this->info("-----------------------------------\n");
                }

                $lastLeadId = $leads->last()->id;
                $leads = (clone $queryBuilder)->where('id', '>', $lastLeadId)->limit($chunk)->get();
            }
        }
    }

}
