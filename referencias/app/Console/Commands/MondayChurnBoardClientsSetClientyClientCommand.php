<?php

namespace App\Console\Commands;

use DateTime;
use Exception;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Models\LeadContactEmail;
use App\Helpers\MondayAPIHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use App\Models\MongoDB\MondayChurnBoardClient;
use App\DTO\Monday\MondayAPIChurnBoardClientDTO;
use App\Services\API\MondayChurnBoardClientService;


//
// @DEPRECATED 29/04/2025, borrar cuando pueda
//
class MondayChurnBoardClientsSetClientyClientCommand extends Command
{

    protected $signature = 'monday-churn-board-clients:set-clienty-client';
    protected $description = 'Set clientyClient in Monday stored churn board clients';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clients = Client::all();
        // $mondayClients = MondayChurnBoardClient::all();
        $mondayClients = MondayChurnBoardClient::whereNull('clientyClient')->get();
        foreach ($mondayClients as $mondayClient) {
            $name = $mondayClient->name ?? null;
            $nameSlug = $name ? Str::slug($name, '') : null;
            $name2 = $mondayClient->formattedValues['name'] ?? null;
            $nameSlug2 = $name2 ? Str::slug($name2, '') : null;
            $email = $mondayClient->formattedValues['email'] ?? null;

            $client = $clients->where('name', 'like', '%' . $name . '%')->first();
            $client = $client ?? $clients->where('name', 'like', '%' . $name2 . '%')->first();
            $client = $client ?? $clients->where('name', 'like', '%' . $nameSlug . '%')->first();
            $client = $client ?? $clients->where('name', 'like', '%' . $nameSlug2 . '%')->first();
            $client = $client ?? $clients->where('subdomain', 'like', '%' . $nameSlug . '%')->first();
            $client = $client ?? $clients->where('subdomain', 'like', '%' . $nameSlug2 . '%')->first();
            $client = $client ?? $clients->filter(function ($c) use ($email, $nameSlug, $nameSlug2) {
                foreach ($c->emails as $clientEmail) {
                    if ($clientEmail == $email) {
                        return true;
                    }
                    if (Str::contains($clientEmail, $nameSlug)) {
                        return true;
                    }
                    if (Str::contains($clientEmail, $nameSlug2)) {
                        return true;
                    }
                    return false;
                }
            })->first();

            if ($client) {
                $mondayClient->clientyClient = $client->toArray();
                $mondayClient->save();

                $this->info(
                    "Clienty Client FOUND {$client->contract_type}" .
                    " -> ID: {$client->id} - name: {$client->name} || " .
                    "Monday client -> name: {$mondayClient->name} - email: {$mondayClient->formattedValues['email']}"
                );
                continue;
            }

            $this->error(
                "Clienty Client NOT FOUND || " .
                "Monday client -> name: {$mondayClient->name} - email: {$mondayClient->formattedValues['email']}"
            );
        }
    }

}
