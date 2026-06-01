<?php

namespace App\Repositories;

use Exception;
use App\Models\Note;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;

class NoteRepository
{

    public function create(array $data): Note
    {
        $note = new Note($data);
        $note->saveOrFail();
        return $note->fresh();
    }


    public function update(Note $note, $text): Note
    {
        $note->fill(['text' => $text]);
        $note->saveOrFail();
        return $note->fresh();
    }


    public function delete(Note $note): Note
    {
        $note->delete();
        return $note->fresh();
    }


    public function bulkInsert(Collection $notesAttrs): bool
    {
        $result = Note::insert($notesAttrs->toArray());
        return $result;
    }

}
