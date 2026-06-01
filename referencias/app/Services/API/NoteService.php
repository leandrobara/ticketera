<?php

namespace App\Services\API;

use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\User;
use App\Models\Note;
use App\Helpers\OpenAIHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Helpers\LeadAudioNoteHelper;
use App\Repositories\NoteRepository;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;


class NoteService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        protected NoteRepository $noteRepository,
        protected ClientEventsDispatcherService $clientEventsDispatcherService,
        protected TimelineEventsDispatcherService $timelineEventsDispatcherService,
    ) {
    }


    public function create(Lead $lead, array $data, ?UploadedFile $audioBlob = null): Note
    {
        if (!isset($data['user_id'])) {
            $data['user_id'] = $this->getUser()->id;
        }
        if (!isset($data['client_id'])) {
            $data['client_id'] = $this->getClient()->id;
        }
        $data['lead_id'] = $lead->id;

        if ($audioBlob) {
            $uploadResponse = resolve(LeadAudioNoteHelper::class)->uploadFile($this->getClient(), $audioBlob);
            $data = $data + [
                'audionote_bucket_hash' => $uploadResponse['hash'],
                'audionote_bucket_file_size' => $audioBlob->getSize(),
                'audionote_bucket_name' => $uploadResponse['bucketName'],
                'audionote_bucket_file_extension' => $audioBlob->extension(),
                'audionote_bucket_filepath' => $uploadResponse['bucketFilepath'],
            ];

            $transcription = resolve(OpenAIHelper::class)->transcribeAudioFromStorage(
                resolve(LeadAudioNoteHelper::class)->getFilesystemDiskName(), $uploadResponse['bucketFilepath']
            );
            if ($transcription) {
                $data['audionote_transcription'] = $transcription;
            }
        }

        $note = $this->noteRepository->create($data);
        $this->timelineEventsDispatcherService->leadNoteCreated($note);
        return $note;
    }


    public function createMassive(Collection $leads, string $noteText): bool
    {
        try {
            DB::beginTransaction();
            foreach ($leads as $lead) {
                $note = $this->create($lead, ['text' => $noteText]);
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return true;
    }


    public function createMultipleForOneLead(Lead $lead, Collection $notesTexts, ?User $user = null): bool
    {
        $userId = $user?->id ?? $lead->user_id;
        foreach ($notesTexts as $noteText) {
            $data = ['text' => $noteText, 'user_id' => $userId, 'client_id' => $lead->client_id];
            $note = $this->create($lead, $data);
        }
        return true;
    }


    public function update(Note $note, $data): Note
    {
        $oldNote = $note->toArray();
        $updated = $this->noteRepository->update($note, $data['text']);
        $this->timelineEventsDispatcherService->leadNoteUpdated($oldNote, $updated);
        return $updated;
    }


    public function delete(Note $note): Note
    {
        $deleted = $this->noteRepository->delete($note);
        $this->timelineEventsDispatcherService->leadNoteDeleted($deleted);
        return $deleted;
    }


    public function bulkInsert(Collection $notesAttrs): Collection
    {
        if ($notesAttrs->isNotEmpty()) {
            $this->noteRepository->bulkInsert($notesAttrs);
        }
        return $notesAttrs;
    }

}
