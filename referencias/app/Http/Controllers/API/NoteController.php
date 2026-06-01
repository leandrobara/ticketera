<?php

namespace App\Http\Controllers\API;

use App\Models\Lead;
use App\Models\Note;
use App\Services\API\NoteService;
use App\Http\Resources\NoteResource;
use App\Helpers\LeadAudioNoteHelper;
use App\Http\Requests\DeleteNoteRequest;
use App\Http\Requests\CreateNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Requests\DownloadAudioNoteRequest;


class NoteController extends BaseAPIController
{

    public function create(Lead $lead, CreateNoteRequest $req)
    {
        $note = resolve(NoteService::class)->create($lead, $req->validatedAttributes(), $req->getAudioBlob());
        return $this->getSuccessResponse((new NoteResource($note))->loadOptionsFromRequest($req));
    }


    public function update(Note $note, UpdateNoteRequest $request)
    {
        $note = resolve(NoteService::class)->update($note, $request->validatedAttributes());
        return $this->getSuccessResponse((new NoteResource($note))->loadOptionsFromRequest($request));
    }


    public function delete(Note $note, DeleteNoteRequest $request)
    {
        $note = resolve(NoteService::class)->delete($note);
        return $this->getSuccessResponse((new NoteResource($note))->loadOptionsFromRequest($request));
    }


    public function getRawAudioNote(Note $note, DownloadAudioNoteRequest $req)
    {
        $rawData = resolve(LeadAudioNoteHelper::class)->getAudioNoteFileRawData($note);
        // SystemHelper::setBinaryDownloadHeaders($note->original_filename, $leadAttachment->size);
        echo $rawData;
    }

}
