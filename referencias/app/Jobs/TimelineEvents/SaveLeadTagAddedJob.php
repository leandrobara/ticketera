<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadTagAddedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public function __construct(
        public ?int $userId,
        public int $leadId,
        public int $tagId,
        public ?DateTime $eventLogDate = null
    ) {
    }


    public function handle()
    {
        $tag = Tag::findOrFail($this->tagId);
        $lead = Lead::findOrFail($this->leadId);
        $user = $this->userId ? resolve(UserService::class)->findOrFail($this->userId) : null;
        if ($tag->client_id != $lead->client_id) {
            throw new Exception('Tag does not belong to lead');
        }
        resolve(EventsLogService::class)->saveLeadTagAdded($user, $tag, $lead, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
