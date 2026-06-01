<?php

namespace App\Http\Controllers\API\Worker;

use App\Models\Lead;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\LeadNotificationEmailService;
use App\Services\API\Dispatchers\ElasticLeadEventsDispatcherService;


// @deprecated NO se usa más elastic
class LeadElasticActionsWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(2000);
    }


    public function checkIndexedLeads(Request $req)
    {
        // $lockIsGranted = resolve(LockHelper::class)->getLockByName('checkIndexedLeads', 10);
        // if (!$lockIsGranted) {
        //     die('Locked');
        // }

        // $limit = $req->input('limit') ?? 300;
        // $minLeadId = $req->input('minLeadId') ?? 0;
        
        // $clientIds = $this->getEnabledClientIds();
        
        // $leadsQuery = $this->getBaseLeadsQueryDB($clientIds);
        // $leadsQuery->where('id', '>', $minLeadId);
        // $leadsCount = $leadsQuery->count();

        // $totalIterations = (int) ($leadsCount / $limit);
        // $dispatcher = resolve(ElasticLeadEventsDispatcherService::class);

        // $iteration = 1;
        // $leads = $leadsQuery->select('id')->limit($limit)->get();
        // while ($leads->isNotEmpty()) {
        //     $leadIds = $leads->pluck('id')->toArray();
        //     $dispatcher->dispatchCheckElasticIndexedLeadsJob($leadIds);

        //     $minLeadId = $leads->last()->id;
        //     echo "- Iteration {$iteration} of {$totalIterations} dispatched. Next MinLeadId: {$minLeadId} \n <br>";
        //     $iteration++;
        //     SystemHelper::doFlush();
            
        //     $minLeadId = $leads->last()->id;
        //     $leadsQuery = $this->getBaseLeadsQueryDB($clientIds);
        //     $leads = $leadsQuery->where('id', '>', $minLeadId)->select('id')->limit($limit)->get();

        //     resolve(LockHelper::class)->getLockByName('checkIndexedLeads', 10);
        // }
    }


    public function addNonIndexedLeadsToElasticIndex(Request $req)
    {
        // $lockIsGranted = resolve(LockHelper::class)->getLockByName('addNonIndexedLeadsToElasticIndex', 10);
        // if (!$lockIsGranted) {
        //     die('Locked');
        // }

        // $limit = $req->input('limit') ?? 300;
        // $minLeadId = $req->input('minLeadId') ?? 0;
        
        // $clientIds = $this->getEnabledClientIds();
        
        // $leadsQuery = $this->getBaseLeadsQueryModel($clientIds);
        // $leadsQuery->where('id', '>', $minLeadId);
        // $leadsCount = $leadsQuery->count();

        // $totalIterations = (int) ($leadsCount / $limit);
        // $dispatcher = resolve(ElasticLeadEventsDispatcherService::class);

        // $iteration = 1;
        // $leads = $leadsQuery->limit($limit)->get();
        // while ($leads->isNotEmpty()) {
        //     foreach ($leads as $lead) {
        //         $lead->searchable();
        //     }

        //     $minLeadId = $leads->last()->id;
        //     echo "- Iteration {$iteration} of {$totalIterations} dispatched. Next MinLeadId: {$minLeadId} \n <br>";
        //     $iteration++;
        //     SystemHelper::doFlush();
            
        //     $leadsQuery = $this->getBaseLeadsQueryModel($clientIds);
        //     $leads = $leadsQuery->where('id', '>', $minLeadId)->limit($limit)->get();

        //     resolve(LockHelper::class)->getLockByName('addNonIndexedLeadsToElasticIndex', 10);
        // }
    }


    protected function getBaseLeadsQueryDB(Collection $clientIds)
    {
        return DB::table('Leads')
            ->whereNull('search_indexed_at')->whereIn('client_id', $clientIds)->whereNull('deleted_at')->orderBy('id')
        ;
    }


    protected function getBaseLeadsQueryModel(Collection $clientIds)
    {
        return Lead::query()
            ->whereNull('search_indexed_at')->whereIn('client_id', $clientIds)->orderBy('id')
        ;
    }


    protected function getEnabledClientIds(): Collection
    {
        return DB::table('Clients')->where('enabled', true)->select('id')->orderBy('id')->get()->pluck('id');
    }

}
