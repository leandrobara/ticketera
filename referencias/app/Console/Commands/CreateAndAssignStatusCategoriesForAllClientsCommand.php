<?php

namespace App\Console\Commands;

use Exception;
use App\Models\Client;
use App\Models\Status;
use App\Models\StatusCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use App\Services\API\StatusService;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\StatusCategoryService;


class CreateAndAssignStatusCategoriesForAllClientsCommand extends Command
{

    protected $signature = 'status-category:create {client_id?}';
    protected $description = 'Create and assign status categories to each client';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clientId = $this->argument('client_id');
        
        $clients = Client::withTrashed()->orderBy('id')->get();
        if ($clientId) {
            $clients = Client::where('id', $clientId)->get();
        }

        $statusService = resolve(StatusService::class);
        $statusCategoryService = resolve(StatusCategoryService::class);

        foreach ($clients as $client) {
            $this->info("##### Client: {$client->name} (ID: {$client->id}) #####");
            $statusCategories = $statusCategoryService->findAllByClient($client);
            if ($statusCategories->isEmpty()) {
                $statusCategories = $statusCategoryService->createNewClientDefaults($client);
                $this->info('StatusCategories created');
            }

            $statusList = Status::withTrashed()->where('client_id', $client->id)->get();
            foreach ($statusList as $status) {
                $this->info("  -> Status '{$status->name}'");
                $this->info("    Current category string: '{$status->category}'");
                $statusCategoryId = $this->getStatusCategoryIdByString($status->category, $statusCategories);
                $statusUpdated = $statusService->update($status, ['status_category_id' => $statusCategoryId]);
                $this->info("    Setted status_category_id: '{$statusCategoryId}'");
                $this->info('');
            }
        }
    }


    private function getStatusCategoryIdByString(string $categoryCode, Collection $statusCategories): int
    {
        $categoryMappings = [
            'new' => 'Nuevo',
            'sale' => 'Con venta',
            'in_process' => 'En proceso',
            'without_sale' => 'Sin venta',
            'irrelevant' => 'Irrelevante',
        ];
        $mappedCategoryName = $categoryMappings[strtolower($categoryCode)];
        $statusCategoryId = $statusCategories->where('name', $mappedCategoryName)->first()->id;
        return $statusCategoryId;
    }

}
