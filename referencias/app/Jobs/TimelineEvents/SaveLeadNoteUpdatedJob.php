<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Note;
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


class SaveLeadNoteUpdatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $userId;
    public $oldNote;
    public $newNoteId;
    public $eventLogDate;
    

    public function __construct(int $userId, array $oldNote, int $newNoteId, ?DateTime $eventLogDate = null)
    {
        $this->userId = $userId;
        $this->oldNote = $oldNote;
        $this->newNoteId = $newNoteId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $newNote = Note::withTrashed()->findOrFail($this->newNoteId);
        $user = resolve(UserService::class)->findOrFail($this->userId);
        resolve(EventsLogService::class)->saveLeadNoteUpdated($user, $this->oldNote, $newNote, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
