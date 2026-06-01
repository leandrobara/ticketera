<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class CreateGodixitalAdminUsersCommand extends Command
{

    protected $signature = 'create:godixital-admin-users';
    protected $description = 'Creates Godixital admin users at agency clients';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clients = Client::where('contract_type', 'godixital')->where('enabled', true)->orderBy('id')->get();
        foreach ($clients as $client) {
            $existentUser = User::where('username', 'godixital')->where('client_id', $client->id)->first();
            if ($existentUser) {
                continue;
            }
            $attrs['client_id'] = $client->id;
            $user = User::factory()->newGodixitalAdminDefault()->create($attrs);
            $this->info("Client: {$client->name} (ID: {$client->id})");
            $this->info("- User created ID: {$user->id}");
            $this->info('------------------------------');
            $this->info('');
        }
    }

}
