<?php

namespace App\Jobs\FacebookEvents;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use App\Services\API\FacebookLogService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class LogLeadGenDataJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $facebookLeadGenData;


    public function __construct(array $facebookLeadGenData)
    {
        $this->facebookLeadGenData = $facebookLeadGenData;
    }


    public function handle()
    {
        resolve(FacebookLogService::class)->saveLeadGenData($this->facebookLeadGenData);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}