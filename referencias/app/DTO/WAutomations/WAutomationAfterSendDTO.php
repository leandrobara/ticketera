<?php

namespace App\DTO\WAutomations;

use App\Models\Client;
use App\Models\Status;
use Illuminate\Support\Collection;


class WAutomationAfterSendDTO
{

    public function __construct(
        public readonly ?Client $client = null,
        public readonly bool $enabled = false,
        public readonly bool $addNewNote = false,
        public readonly bool $applyOnlyOnce = true,
        public readonly ?string $newNoteText = null,
        public readonly ?Status $statusToAssign = null,
        public readonly Collection $tagsToAdd = new Collection(),
        public readonly bool $onlyApplyToMassiveSendings = false,
        public readonly Collection $tagsToRemove = new Collection(),
    ) {
    }

}
