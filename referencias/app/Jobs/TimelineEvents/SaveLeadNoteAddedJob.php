<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
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


class SaveLeadNoteAddedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $userId;
    public $noteId;
    public $eventLogDate;


    public function __construct(?int $userId, int $noteId, ?DateTime $eventLogDate = null)
    {
        $this->userId = $userId;
        $this->noteId = $noteId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $note = Note::find($this->noteId);
        if (!$note) {
            return true;
        }
        $user = $this->userId ? resolve(UserService::class)->findOrFail($this->userId) : null;
        resolve(EventsLogService::class)->saveLeadNoteAdded($user, $note, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
