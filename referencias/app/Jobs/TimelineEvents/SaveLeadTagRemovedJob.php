<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Exception;
use Throwable;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use App\Services\API\EventsLogService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadTagRemovedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $tagId;
    public $userId;
    public $leadId;
    public $eventLogDate;
    

    public function __construct(?int $userId, int $leadId, int $tagId, ?DateTime $eventLogDate = null)
    {
        $this->tagId = $tagId;
        $this->userId = $userId;
        $this->leadId = $leadId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $tag = Tag::withTrashed()->findOrFail($this->tagId);
        $lead = Lead::findOrFail($this->leadId);
        $user = $this->userId ? resolve(UserService::class)->findOrFail($this->userId) : null;

        if ($tag->client_id != $lead->client_id) {
            throw new Exception('Tag does not belong to lead');
        }
        resolve(EventsLogService::class)->saveLeadTagRemoved($user, $lead, $tag, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
