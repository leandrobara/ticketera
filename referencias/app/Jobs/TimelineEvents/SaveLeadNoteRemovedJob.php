<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use App\Models\Note;
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


class SaveLeadNoteRemovedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    

    public function __construct(public int $userId, public int $noteId, public ?DateTime $eventLogDate = null)
    {
    }


    public function handle()
    {
        $note = Note::withTrashed()->findOrFail($this->noteId);
        $user = resolve(UserService::class)->findOrFail($this->userId);
        resolve(EventsLogService::class)->saveLeadNoteRemoved($user, $note, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
