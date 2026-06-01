<?php

namespace App\Jobs\SearchLeadEvents;

use Throwable;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use App\Helpers\MongoSearchHelper;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\SearchLeadEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class MultipleUpdateLeadSearchInfoJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $backoff = 60;
    
    public $leadIds;
    

    public function __construct(array $leadIds)
    {
        $this->leadIds = $leadIds;
    }


    public function handle()
    {
        $leads = Lead::where('id', $this->leadIds)->get();
        if ($leads->isEmpty()) {
            return null;
        }
        foreach ($leads as $lead) {
            $mongoId = resolve(MongoSearchHelper::class)->addOrReplaceLead($lead);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
