<?php

namespace App\Jobs\SearchLeadEvents;

use DateTime;
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


class CheckSearchIndexedLeadsJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $leadIds;
    public $timeout = 30;
    

    public function __construct(array $leadIds)
    {
        $this->leadIds = $leadIds;
    }


    public function handle()
    {
        if (!$this->leadIds) {
            return true;
        }

        $helper = resolve(MongoSearchHelper::class);
        $docs = $helper->findDocumentsByLeadIds($this->leadIds, ['fields' => ['id']]);
        
        // dump('LeadIds: ' . implode(',', $this->leadIds));
        // dump('----');
        // dump('Docs: ' . implode(',', $docs->pluck('id')->toArray()));

        if ($docs->isEmpty()) {
            return true;
        }

        $indexedLeadIds = $docs->pluck('id');
        Lead::whereIn('id', $indexedLeadIds)->update(['search_indexed_at' => new DateTime()]);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
