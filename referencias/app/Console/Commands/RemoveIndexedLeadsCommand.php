<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class RemoveIndexedLeadsCommand extends Command
{

    protected $leadIndex = 0;
    protected $description = 'Remove indexed leads in ElasticSearch';
    protected $signature = 'index:remove-leads {--chunk=} {--client-id=} {--ids=*}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $leadIds = $this->option('ids') ?? [];
        $chunk = (int) ($this->option('chunk') ?? 1000);
        $clientId = (int) ($this->option('client-id') ?? 0);
        
        $this->warn('This command reindex all leads\' data, it may take a long time!');

        $queryBuilder = Lead::query();
        if ($clientId) {
            $queryBuilder->where('client_id', $clientId);
        }
        if ($leadIds) {
            $queryBuilder->whereIn('id', $leadIds);
        }
        $leadsCount = $queryBuilder->count();
        
        $queryBuilder->chunk($chunk, function ($leads) use ($leadsCount) {
            foreach ($leads as $lead) {
                $lead->unsearchable();
                $this->info(
                    "Lead No: {$this->leadIndex} of {$leadsCount} with id: {$lead->id} has been removed from index"
                );
                $this->leadIndex++;
            }
        });
    }

}
