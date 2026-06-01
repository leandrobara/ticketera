<?php

namespace App\Http\Controllers\API\Worker;

use App\Models\Lead;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Helpers\MongoSearchHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Dispatchers\SearchLeadEventsDispatcherService;



class LeadSearchActionsWorkerController extends BaseAPIController
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
        die('DEPRECATED');

        // $lockIsGranted = resolve(LockHelper::class)->getLockByName('checkIndexedLeads', 10);
        // if (!$lockIsGranted) {
        //     die('Locked');
        // }

        // $chunk = $req->input('chunk') ?? 300;
        // $clientIds = $this->getEnabledClientIds();

        // $minLeadId = $req->input('minLeadId') ?? 0;
        // $minLeadId = $minLeadId ?: Cache::get('checkIndexedLeads_minLeadId');
        // $minLeadId = $minLeadId ?: $this->findNonIndexedMinLeadId($clientIds);
        
        // $leadsQuery = $this->getBaseLeadsQueryDB($clientIds);
        // $leadsQuery->where('id', '>', $minLeadId);
        // $leadsCount = $leadsQuery->count();

        // $totalIterations = (int) ($leadsCount / $chunk);
        // $dispatcher = resolve(SearchLeadEventsDispatcherService::class);

        // $iteration = 1;
        // $leads = $leadsQuery->select('id')->limit($chunk)->get();
        // while ($leads->isNotEmpty()) {
        //     $leadIds = $leads->pluck('id')->toArray();
        //     $dispatcher->dispatchCheckSearchIndexedLeadsJob($leadIds);

        //     $minLeadId = $leads->last()->id;
        //     echo "- Iteration {$iteration} of {$totalIterations} dispatched. Next MinLeadId: {$minLeadId} \n <br>";
        //     $iteration++;
        //     SystemHelper::doFlush();
            
        //     $minLeadId = $leads->last()->id;
        //     $leadsQuery = $this->getBaseLeadsQueryDB($clientIds);
        //     $leads = $leadsQuery->where('id', '>', $minLeadId)->select('id')->limit($chunk)->get();

        //     resolve(LockHelper::class)->getLockByName('checkIndexedLeads', 10);
        // }
    }


    public function addNonIndexedLeadsToSearchIndex(Request $req)
    {
        $lockKey = 'addNonIndexedLeadsToSearchIndex';
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, 30);
        if (!$lockIsGranted) {
            die('Locked');
        }

        $chunk = $req->input('chunk') ?? 500;
        $clientIds = $this->getEnabledClientIds();
        
        $minLeadId = $req->input('minLeadId') ?? 0;
        $minLeadId = $minLeadId ?: Cache::get('addNonIndexedLeadsToSearchIndex_minLeadId');
        $minLeadId = $minLeadId ?: $this->findNonIndexedMinLeadId($clientIds);
        $minLeadId = $minLeadId ?: 0;
        $minLeadId = intval($minLeadId);
        echo "- FIRST minLeadId: {$minLeadId} \n <br> \n <br>";
        
        $leadsQuery = $this->getBaseLeadsQueryDB($clientIds, $minLeadId);
        $leadsCount = $leadsQuery->count();

        $totalIterations = (int) ($leadsCount / $chunk);
        $helper = resolve(MongoSearchHelper::class);
        $dispatcher = resolve(SearchLeadEventsDispatcherService::class);

        $iteration = 1;
        $leads = $leadsQuery->select('id')->orderBy('id', 'asc')->limit($chunk)->get();
        while ($leads->isNotEmpty()) {
            foreach ($leads as $lead) {
                $dispatcher->dispatchUpdateLeadSearchInfoJob($lead->id);
            }

            $minLeadId = $leads->last()->id;
            echo "- Iteration {$iteration} of {$totalIterations} dispatched. ";
            echo "Next MinLeadId: {$minLeadId} \n <br>";
            
            Cache::set('addNonIndexedLeadsToSearchIndex_minLeadId', $minLeadId, 60 * 60 * 24 * 7);
            
            $iteration++;
            SystemHelper::doFlush();
            
            $leadsQuery = $this->getBaseLeadsQueryDB($clientIds, $minLeadId);
            $leads = $leadsQuery->select('id')->orderBy('id', 'asc')->limit($chunk)->get();

            resolve(LockHelper::class)->getLockByName($lockKey, 30);
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    protected function getBaseLeadsQueryDB(Collection $clientIds, int $minLeadId)
    {
        return DB::table('Leads')
            ->whereNull('deleted_at')
            ->where('id', '>', $minLeadId)
            ->whereNull('search_indexed_at')
            ->whereIn('client_id', $clientIds)
        ;
    }


    // protected function getBaseLeadsQueryModel(Collection $clientIds)
    // {
    //     return Lead::query()
    //         ->whereNull('search_indexed_at')->whereIn('client_id', $clientIds)->orderBy('id')
    //     ;
    // }


    protected function findNonIndexedMinLeadId(Collection $clientIds): ?int
    {
        $lead = DB::table('Leads')
            ->select('id')
            ->whereNull('deleted_at')
            ->whereNull('search_indexed_at')
            ->whereIn('client_id', $clientIds)
            ->orderBy('id', 'asc')
            ->limit(1)
            ->first()
        ;
        return $lead?->id ?? null;
    }


    protected function getEnabledClientIds(): Collection
    {
        return DB::table('Clients')->where('enabled', true)->select('id')->orderBy('id')->get()->pluck('id');
    }

}
