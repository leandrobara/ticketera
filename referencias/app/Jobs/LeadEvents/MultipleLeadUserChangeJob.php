<?php

namespace App\Jobs\LeadEvents;

use Throwable;
use App\Models\User;
use App\Models\Lead;
use App\Models\Status;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\Actions\LeadService;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;


class MultipleLeadUserChangeJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    
    public $leadIds;
    public $newUserId;
    public $loginUserId;
    

    public function __construct(array $leadIds, int $newUserId, int $loginUserId)
    {
        $this->leadIds = $leadIds;
        $this->newUserId = $newUserId;
        $this->loginUserId = $loginUserId;
    }


    public function handle()
    {
        $leadService = resolve(LeadService::class);
        $newUser = resolve(UserService::class)->findOrFail($this->newUserId);
        $timelineDispatcherService = resolve(TimelineEventsDispatcherService::class);
        $loginUser = $this->loginUserId ? resolve(UserService::class)->findOrFail($this->loginUserId) : null;

        $oldUserIds = [];
        $leads = Lead::whereIn('id', $this->leadIds)->where('client_id', $newUser->client_id)->get();
        foreach ($leads as $lead) {
            array_push($oldUserIds, $lead->user_id);
            $leadService->changeUser($lead, $newUser);
        }

        $delaySeconds = 20;
        $timelineDispatcherService->multipleLeadUserUpdated(
            $this->leadIds, $oldUserIds, $newUser, $loginUser, $delaySeconds
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
