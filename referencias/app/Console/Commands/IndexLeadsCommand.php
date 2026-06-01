<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;
use App\Helpers\MongoSearchHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\Dispatchers\SearchLeadEventsDispatcherService;


class IndexLeadsCommand extends Command
{

    protected $leadIndex = 0;
    protected $description = 'Index Leads in MongoDB full text search';
    protected $signature =
        'index:leads {--chunk=} {--client-id=} {--index-method=} {--min-id=} {--not-indexed-only=} {--ids=*}'
    ;
    
    protected $indexMethod;
    protected $mongoSearchHelper;
    protected $searchLeadEventsDispatcherService;


    public function __construct()
    {
        parent::__construct();
        $this->mongoSearchHelper = resolve(MongoSearchHelper::class);
        $this->searchLeadEventsDispatcherService = resolve(SearchLeadEventsDispatcherService::class);
    }

    public function handle()
    {
        // job | direct
        $indexMethod = $this->option('index-method') ?? 'job';

        $leadIds = $this->option('ids') ?? [];
        $chunk = (int) ($this->option('chunk') ?? 1000);
        $minId = (int) ($this->option('min-id') ?? 0);
        $clientId = (int) ($this->option('client-id') ?? 0);
        $notIndexedOnly = (int) ($this->option('not-indexed-only') ?? false);
        $this->warn('This command reindex all leads data, it may take a long time!');

        $queryBuilder = Lead::query();
        if ($clientId) {
            $queryBuilder->where('client_id', $clientId);
        }
        if ($minId) {
            $queryBuilder->where('id', '>', $minId);
        }
        if ($leadIds) {
            $queryBuilder->whereIn('id', $leadIds);
        }
        if ($notIndexedOnly) {
            $queryBuilder->whereNotNull('search_indexed_at');
        }
        $leadsCount = $queryBuilder->count();

        $indexWithMongo = $indexMethod == 'direct';
        $queryBuilder->chunk($chunk, function ($leads) use ($leadsCount, $indexWithMongo) {
            foreach ($leads as $lead) {
                if ($indexWithMongo) {
                    resolve(MongoSearchHelper::class)->addOrReplaceLead($lead);
                } else {
                    $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
                }

                $this->info("Lead No: {$this->leadIndex} of {$leadsCount} with id: {$lead->id} has been indexed");
                $this->leadIndex++;
            }
            
            if (!$indexWithMongo) {
                $queueName = config('queue.lead_search_events');
                $queryBuilder = DB::connection('mysql_worker')->table('jobs')->where('queue', $queueName);
                $jobsCount = $queryBuilder->count();
                while ($jobsCount > 0) {
                    $this->info("Remaining jobs: {$jobsCount}. Sleeping 4 seconds.");
                    sleep(4);
                    $jobsCount = $queryBuilder->count();
                }
            }
        });
    }

}
