<?php

namespace App\Repositories;

use Exception;
use App\Models\Lead;
use App\Models\EmailDraft;
use App\Exceptions\DatabaseException;


/**
 * Info: It does not use SoftDeletes.
 */
class EmailDraftRepository
{

    public function saveLeadEmailDraft(Lead $lead, array $emailDraftData): EmailDraft
    {

        $emailDraft = $lead->emailDraft ?? new EmailDraft();
        $emailDraft->fill($emailDraftData);
        $emailDraft->lead_id = $lead->id;
        $emailDraft->client_id = $lead->client_id;
        $emailDraft->saveOrFail();
        return $emailDraft->fresh();
    }


    /**
     * Info: It does not use SoftDeletes. That is why it return boolean.
     */
    public function deleteByLead(Lead $lead): bool
    {
        if ($lead->emailDraft) {
            $lead->emailDraft->delete();
        }
        return true;
    }

}
