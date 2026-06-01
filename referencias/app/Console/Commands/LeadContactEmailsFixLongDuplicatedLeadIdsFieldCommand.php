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


class LeadContactEmailsFixLongDuplicatedLeadIdsFieldCommand extends Command
{

    protected $processedIds = [];
    protected $description = 'Fix duplicated long lead ids at LeadContactEmails table';
    protected $signature = 'lead-contact-emails:fix-long-duplicated-lead-ids {--chunk=} {--min-id=} {--client-id=}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $chunk = (int) ($this->option('chunk') ?? 5000);
        $clientId = (int) ($this->option('client-id') ?? 0);
        $minLeadContactEmailId = (int) ($this->option('min-id') ?? 0);

        $queryBuilder = LeadContactEmail::query();
        if ($minLeadContactEmailId) {
            $queryBuilder->where('id', '>=', $minLeadContactEmailId);
        }
        $queryBuilder = $queryBuilder->whereRaw('LENGTH(lead_ids_where_repeated) > 1000');
        $queryBuilder = $queryBuilder->select('id', 'email', 'client_id', 'lead_ids_where_repeated');
        $leadContactEmails = $queryBuilder->limit(200)->get();

        while ($leadContactEmails->isNotEmpty()) {
            foreach ($leadContactEmails as $leadContactEmail) {
                $repeatedLeadIds = collect($leadContactEmail->lead_ids_where_repeated);
                $chunkedRepeatedLeadIds = $repeatedLeadIds->take(100);

                $leadContactEmail->lead_ids_where_repeated = $chunkedRepeatedLeadIds->toArray();
                $leadContactEmail->saveOrFail();

                $this->info("- LCE ID: {$leadContactEmail->id}");
            }
            $leadContactEmails = $queryBuilder->limit(200)->get();
        }
    }

}
