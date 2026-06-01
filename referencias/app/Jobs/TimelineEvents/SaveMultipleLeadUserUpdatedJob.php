<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveMultipleLeadUserUpdatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $leadIds;
    public $newUserId;
    public $oldUserIds;
    public $loginUserId;
    public $eventLogDate;

    
    public function __construct(
        array $leadIds,
        array $oldUserIds,
        int $newUserId,
        ?int $loginUserId,
        ?DateTime $eventLogDate
    ) {
        $this->leadIds = $leadIds;
        $this->newUserId = $newUserId;
        $this->oldUserIds = $oldUserIds;
        $this->loginUserId = $loginUserId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $service = resolve(EventsLogService::class);
        $newUser = resolve(UserService::class)->findOrFail($this->newUserId);
        $leads = Lead::whereIn('id', $this->leadIds)->where('client_id', $newUser->client_id)->get();
        $users = User::where('client_id', $newUser->client_id)->get();
        $loginUser = $this->loginUserId ? resolve(UserService::class)->findOrFail($this->loginUserId) : null;
        
        foreach ($leads as $i => $lead) {
            $oldUserId = $this->oldUserIds[$i];
            $oldUser = $users->where('id', $oldUserId)->first()->toArray();
            $service->saveLeadUserUpdated(
                $oldUser, $newUser, $lead, $loginUser, $this->eventLogDate
            );
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
