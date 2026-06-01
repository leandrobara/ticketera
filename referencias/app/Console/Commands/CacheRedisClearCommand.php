<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class CacheRedisClearCommand extends Command
{

    protected $signature = 'cache:redis-clear';
    protected $description = 'Delete all redis cache';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        resolve(RedisHelper::class)->deleteAll();
        $this->info('All Redis cache cleared!');
    }

}
