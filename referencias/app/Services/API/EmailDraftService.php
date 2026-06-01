<?php

namespace App\Services\API;

use App\Models\Lead;
use App\Models\EmailDraft;
use App\Repositories\EmailDraftRepository;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;


/**
 * Info: It does not use SoftDeletes.
 */
class EmailDraftService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $emailDraftRepository;


    public function __construct(EmailDraftRepository $emailDraftRepository)
    {
        $this->emailDraftRepository = $emailDraftRepository;
    }


    public function findOneByLead(Lead $lead): ?EmailDraft
    {
        return $lead->emailDraft;
    }


    public function saveLeadEmailDraft(Lead $lead, array $emailDraftData): EmailDraft
    {
        return $this->emailDraftRepository->saveLeadEmailDraft($lead, $emailDraftData);
    }


    public function deleteByLead(Lead $lead): bool
    {
        return $this->emailDraftRepository->deleteByLead($lead);
    }

}
