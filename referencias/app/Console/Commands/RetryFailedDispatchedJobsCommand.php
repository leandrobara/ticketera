<?php

namespace App\Console\Commands;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\FailedDispatchedJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Bus\PendingDispatch;


class RetryFailedDispatchedJobsCommand extends Command
{

    protected $index = 1;
    protected $description = 'Put back failed dispatched jobs into jobs queue';
    protected $signature = 'queue:dispatch:retry {--chunk=} {--client-id=} {--min-id=} {--id=} {--ids=*}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $ids = $this->option('ids') ?? [];
        $id = (int) ($this->option('id') ?? 0);
        $chunk = (int) ($this->option('chunk') ?? 1000);
        $minId = (int) ($this->option('min-id') ?? 0);
        $clientId = (int) ($this->option('client-id') ?? 0);

        $queryBuilder = FailedDispatchedJob::query();
        if ($clientId) {
            $queryBuilder->where('client_id', $clientId);
        }
        if ($minId) {
            $queryBuilder->where('id', '>=', $minId);
        }
        if ($id) {
            $queryBuilder->where('id', $id);
        }
        if ($ids) {
            $queryBuilder->where('ids', $ids);
        }
        $jobsCount = $queryBuilder->count();
        
        $queryBuilder->chunk($chunk, function ($failedDispatchedJobs) use ($jobsCount) {
            foreach ($failedDispatchedJobs as $failedDispatchedJob) {
                $job = $failedDispatchedJob->unserializedJob;
                $isJob = Str::contains(get_class($job), 'App\Jobs');
                $isBrowserEvent = Str::contains(get_class($job), 'App\Events');

                $hasDelay = property_exists($job, 'delay') && $job->delay;
                if ($hasDelay) {
                    $job->delay = null;
                }

                if ($isJob) {
                    $pendingDispatch = new PendingDispatch($job);

                    $hasDelay = property_exists($job, 'delay') && $job->delay;
                    if ($hasDelay) {
                        $delayDate = Carbon::parse($job->delay);
                        $createdDate = Carbon::parse($failedDispatchedJob->created_at);
                        $differenceInSeconds = $delayDate->diffInSeconds($createdDate);
                        $pendingDispatch->delay(now()->addSeconds($differenceInSeconds));
                    }
                } elseif ($isBrowserEvent) {
                    // From vendor/laravel/framework/src/Illuminate/Foundation/helpers.php
                    broadcast($job);
                } else {
                    throw new Exception('Not recognized job/event class');
                }

                $msg = implode('', [
                    "- Failed Dispatched Job No: {$this->index} of {$jobsCount} with id: ",
                    "{$failedDispatchedJob->id} has been dispatched",
                ]);
                $failedDispatchedJob->delete();
                $this->info($msg);
                $this->index++;
            }
        });
    }

}
