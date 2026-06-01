<?php

namespace App\Console\Commands;

use Exception;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\LeadCustomFieldService;


class LeadCustomFieldSetOrderCommand extends Command
{

    protected $description = 'Set an order to custom fields';
    protected $signature = 'lead-custom-field:set-order {--client-id=}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clientId = (int) ($this->option('client-id') ?? 0);

        $queryBuilder = Client::query();

        if ($clientId) {
            $queryBuilder->where('id', $clientId);
        }

        $clients = $queryBuilder->withTrashed()->orderBy('id', 'asc')->get();
        foreach ($clients as $client) {
            $this->info("\n-----------------------------------");
            $this->info("- Client ID: {$client->id} -> {$client->name}");
            $this->info("-----------------------------------\n");

            $leadCustomFields = $this->reOrderAll($client);
            
            $this->info("End: Lead Custom Fields ordered");
        }
    }


    protected function reOrderAll(Client $client): void
    {
        $order = 0;
        $leadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient($client);
        foreach ($leadCustomFields as $leadCustomField) {
            $leadCustomField->order = $order;
            $leadCustomField->saveOrFail();
            $order++;

            $this->info("\"{$leadCustomField->name}\" (ID: {$leadCustomField->id}) - ordered");
        }
    }

}
