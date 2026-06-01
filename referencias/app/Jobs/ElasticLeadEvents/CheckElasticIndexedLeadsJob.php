<?php

namespace App\Jobs\ElasticLeadEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use App\Helpers\ElasticSearchHelper;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\ElasticLeadEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


// @DEPRECATED
class CheckElasticIndexedLeadsJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $leadIds;
    

    public function __construct(array $leadIds)
    {
        $this->leadIds = $leadIds;
    }


    public function handle()
    {
        if (!$this->leadIds) {
            return true;
        }

        $helper = resolve(ElasticSearchHelper::class);
        $elasticDocs = $helper->findDocumentsByLeadIds($this->leadIds, ['fields' => ['id']]);
        
        // dump('LeadIds: ' . implode(',', $this->leadIds));
        // dump('----');
        // dump('Docs: ' . implode(',', $elasticDocs->pluck('id')->toArray()));

        if ($elasticDocs->isEmpty()) {
            return true;
        }

        $indexedLeadIds = $elasticDocs->pluck('id');
        Lead::whereIn('id', $indexedLeadIds)->update(['search_indexed_at' => new DateTime()]);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
