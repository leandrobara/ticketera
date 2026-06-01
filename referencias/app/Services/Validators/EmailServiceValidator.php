<?php

namespace App\Services\Validators;

use App\Models\User;
use App\DTO\EmailSendParametersDTO;
use App\DTO\EmailScheduleParametersDTO;
use App\DTO\EmailMassiveSendParametersDTO;
use App\DTO\EmailSystemScheduleParametersDTO;
use App\DTO\EmailMassiveScheduleParametersDTO;
use App\Exceptions\Services\EmailService\EmailSendValidationException;
use App\Exceptions\Services\EmailService\EmailSendValidationUserNotEnabledException;


class EmailServiceValidator
{

    public function validateUserEmailSendingEnabled(User $user): void
    {
        if (!$user['email_is_verified']) {
            throw new EmailSendValidationUserNotEnabledException('user_email_not_verified');
        }
        if (!$user['email_from_address']) {
            throw new EmailSendValidationUserNotEnabledException('user_email_from_address_empty');
        }
        if (!$user['email_from_name']) {
            throw new EmailSendValidationUserNotEnabledException('user_email_from_name_empty');
        }
    }


    public function validateEmailSendParameters(EmailSendParametersDTO $sendParametersDTO): void
    {
        if (!$sendParametersDTO->body) {
            throw new EmailSendValidationException('body_do_not_exists');
        }
        if (!$sendParametersDTO->subject) {
            throw new EmailSendValidationException('subject_do_not_exists');
        }
    }


    public function validateEmailSendToLeadParameters(EmailSendParametersDTO $dto): void
    {
        $this->validateEmailSendParameters($dto);
        if (!$dto->leadContactEmails || $dto->leadContactEmails->isEmpty()) {
            throw new EmailSendValidationException('lead_contact_emails_do_not_exist');
        }
        if (!$dto->individualLeadSendHash) {
            throw new EmailSendValidationException('individual_lead_send_hash_does_not_exist');
        }
    }


    public function validateEmailMassiveSendParameters(EmailMassiveSendParametersDTO $sendParametersDTO): void
    {
        if (!$sendParametersDTO->body) {
            throw new EmailSendValidationException('body_do_not_exists');
        }
        if (!$sendParametersDTO->subject) {
            throw new EmailSendValidationException('subject_do_not_exists');
        }
    }


    public function validateEmailMassiveScheduleParameters(EmailMassiveScheduleParametersDTO $dto): void
    {
        if (!$dto->body) {
            throw new EmailSendValidationException('body_do_not_exists');
        }
        if (!$dto->subject) {
            throw new EmailSendValidationException('subject_do_not_exists');
        }
        if (!$dto->sendDate) {
            throw new EmailSendValidationException('send_date_do_not_exists');
        }
    }


    public function validateEmailScheduleParameters(EmailScheduleParametersDTO $dto): void
    {
        if (!$dto->body) {
            throw new EmailSendValidationException('body_do_not_exists');
        }
        if (!$dto->subject) {
            throw new EmailSendValidationException('subject_do_not_exists');
        }
        if (!$dto->sendDate) {
            throw new EmailSendValidationException('send_date_do_not_exists');
        }
    }

    public function validateSystemEmailScheduleParameters(EmailSystemScheduleParametersDTO $dto): void
    {
        if (!$dto->body) {
            throw new EmailSendValidationException('body_do_not_exists');
        }
        if (!$dto->subject) {
            throw new EmailSendValidationException('subject_do_not_exists');
        }
        if (!$dto->sendDate) {
            throw new EmailSendValidationException('send_date_do_not_exists');
        }
    }

}
