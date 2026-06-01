<?php

namespace App\Jobs\SearchLeadEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use App\Helpers\MongoSearchHelper;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\SearchLeadEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class UpdateLeadSearchInfoJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $backoff = 60;
    
    public $leadId;
    

    public function __construct(int $leadId)
    {
        $this->leadId = $leadId;
    }


    public function handle()
    {
        $key = 'UpdateLeadSearchInfoJob:handle:leadId:' . $this->leadId;
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($key, 5);
        if (!$lockIsGranted) {
            return null;
        }

        $lead = Lead::find($this->leadId);
        if (!$lead) {
            return null;
        }
        $mongoId = resolve(MongoSearchHelper::class)->addOrReplaceLead($lead);
        if ($mongoId) {
            Lead::where('id', $lead->id)->update(['search_indexed_at' => new DateTime()]);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
