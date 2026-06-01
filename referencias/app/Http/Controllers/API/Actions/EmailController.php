<?php

namespace App\Http\Controllers\API\Actions;

use App\Models\Lead;
use App\Models\Email;
use App\Helpers\SystemHelper;
use App\Models\LeadContactEmail;
use App\Services\API\EmailService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\Emails\EmailSendToLeadRequest;
use App\Http\Requests\Actions\Emails\EmailSendMassiveRequest;
use App\Http\Requests\Actions\Emails\EmailCancelMassiveRequest;
use App\Http\Requests\Actions\Emails\EmailCancelMultipleRequest;
use App\Http\Requests\Actions\Emails\EmailScheduleToLeadRequest;
use App\Http\Requests\Actions\Emails\EmailScheduleMassiveRequest;
use App\Http\Requests\Actions\Emails\EmailSendToLeadContactEmailRequest;
use App\Http\Requests\Actions\Emails\EmailScheduleToLeadContactEmailRequest;


class EmailController extends BaseAPIController
{

    public function sendToLead(Lead $lead, EmailSendToLeadRequest $req)
    {
        $sentEmails = resolve(EmailService::class)->sendToLead($lead, $req->validatedDTO());
        return $this->getSuccessResponse($sentEmails);
    }


    public function scheduleToLead(Lead $lead, EmailScheduleToLeadRequest $req)
    {
        $scheduledEmails = resolve(EmailService::class)->scheduleToLead($lead, $req->validatedDTO());
        return $this->getSuccessResponse($scheduledEmails);
    }


    public function sendToLeadContactEmail(
        Lead $lead,
        LeadContactEmail $leadContactEmail,
        EmailSendToLeadContactEmailRequest $req
    ) {
        $email = resolve(EmailService::class)->sendToLeadContactEmail($leadContactEmail, $req->validatedDTO());
        return $this->getSuccessResponse($email);
    }


    public function scheduleToLeadContactEmail(
        Lead $lead,
        LeadContactEmail $leadContactEmail,
        EmailScheduleToLeadContactEmailRequest $req
    ) {
        $email = resolve(EmailService::class)->scheduleToLeadContactEmail($leadContactEmail, $req->validatedDTO());
        return $this->getSuccessResponse($email);
    }


    public function sendMassive(EmailSendMassiveRequest $req)
    {
        SystemHelper::setTimeLimit(120);
        SystemHelper::setMemoryLimitMB(500);

        $response = resolve(EmailService::class)->sendMassiveEmail($req->validatedDTO());
        return $this->getSuccessResponse($response);
    }


    public function scheduleMassive(EmailScheduleMassiveRequest $req)
    {
        SystemHelper::setTimeLimit(120);
        SystemHelper::setMemoryLimitMB(500);

        $response = resolve(EmailService::class)->scheduleMassiveEmail($req->validatedDTO());
        return $this->getSuccessResponse($response);
    }


    public function cancelMultiple(EmailCancelMultipleRequest $req)
    {
        $cancelledEmail = resolve(EmailService::class)->cancelEmails($req->getEmailsToCancel());
        return $this->getSuccessResponse($cancelledEmail);
    }


    public function cancelMassive(EmailCancelMassiveRequest $req)
    {
        $cancelledEmailIds = resolve(EmailService::class)->cancelMassiveEmail($req->getExternalMassiveId());
        return $this->getSuccessResponse($cancelledEmailIds);
    }

}
