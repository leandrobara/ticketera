<?php

namespace App\Jobs\LeadEvents;

use DateTime;
use Throwable;
use Exception;
use Illuminate\Bus\Queueable;
use App\Models\LeadContactEmail;
use App\Helpers\EmailValidatorHelper;
use App\Helpers\IPQualityScoreHelper;
use Illuminate\Queue\SerializesModels;
use App\Helpers\MailsSoValidatorHelper;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\Actions\LeadService;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Helpers\EmailListVerifyValidatorHelper;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Models\MongoDB\EmailValidationResponseLog;


class ValidateLeadContactEmailJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $nullStatusCode;
    public $validStatusCode;
    public $leadContactEmail;
    public $invalidStatusCode;
    public $skippedStatusCode;
    public $leadContactEmailId;
    public $lastValidationStatusCode;


    public function __construct(int $leadContactEmailId)
    {
        $this->leadContactEmailId = $leadContactEmailId;
        $this->nullStatusCode = LeadContactEmail::EMAIL_VALIDATOR_NULL_STATUS;
        $this->validStatusCode = LeadContactEmail::EMAIL_VALIDATOR_VALID_STATUS;
        $this->skippedStatusCode = LeadContactEmail::EMAIL_VALIDATOR_SKIPPED_STATUS;
        $this->invalidStatusCode = LeadContactEmail::EMAIL_VALIDATOR_INVALID_STATUS;
    }


    public function handle()
    {
        $this->leadContactEmail = LeadContactEmail::find($this->leadContactEmailId);
        if (!$this->leadContactEmail) {
            return true;
        }
        if ($this->leadContactEmail->bounced) {
            return true;
        }
        if ($this->leadContactEmail->complained) {
            return true;
        }
        if ($this->leadContactEmail->unsubscribed) {
            return true;
        }
        if (!$this->leadContactEmail->is_valid) {
            return true;
        }
        
        // Validación con EmailValidatorHelper
        if (!$this->leadContactEmail->wasValidatedWithEmailValidator()) {
            $result = resolve(EmailValidatorHelper::class)->emailExists($this->leadContactEmail->email);
            $isValid = $result['is_valid'] ?? true;
            $isDecisive = $result['is_decisive'] ?? false;

            $validationStatusCode = $isValid ? $this->validStatusCode : $this->invalidStatusCode;
            $this->lastValidationStatusCode = $validationStatusCode;

            $this->leadContactEmail->is_valid = $isValid;
            $this->leadContactEmail->setEmailValidatorValidationStatus($validationStatusCode);
            $this->leadContactEmail->saveOrFail();

            // Si hizo una validación, y determina que el resultado es decisivo, no paso al siguiente.
            if ($isDecisive) {
                return true;
            }
        }

        $this->validateWithMailsSo();
        // Si no se marcó como válido por Mails.SO, lo valido con EmailListVerify
        if ($this->lastValidationStatusCode != $this->validStatusCode) {
            $this->validateWithEmailListVerify();
        }

        if (!$this->leadContactEmail->is_valid) {
            $this->assignInvalidEmailTag();
        }
    }


    protected function assignInvalidEmailTag(): bool
    {
        $clientSettings = $this->leadContactEmail->client->clientSettings;
        if ($clientSettings->see_disabled_email_reason_as_label) {
            $lead = $this->leadContactEmail->lead()->withTrashed()->first();
            resolve(LeadService::class)->assignInvalidEmailTag($lead);
        }
        return true;
    }


    protected function validateWithMailsSo(): bool
    {
        if ($this->leadContactEmail->wasValidatedWithMailsSo()) {
            return true;
        }

        $isValid = resolve(MailsSoValidatorHelper::class)->isValidEmail($this->leadContactEmail->email);
        if ($isValid === true) {
            $this->leadContactEmail->is_valid = true;
            $validationStatus = $this->validStatusCode;
        }
        // Si es null, dejo "leadContactEmail->is_valid" como estaba.
        if ($isValid === null) {
            $validationStatus = $this->nullStatusCode;
        }
        if ($isValid === false) {
            $this->leadContactEmail->is_valid = false;
            $validationStatus = $this->invalidStatusCode;
        }

        $this->lastValidationStatusCode = $validationStatus;
        $this->leadContactEmail->setMailsSoValidationStatus($validationStatus);
        $this->leadContactEmail->saveOrFail();
        return true;
    }


    protected function validateWithEmailListVerify(): bool
    {
        if ($this->leadContactEmail->wasValidatedWithEmailListVerify()) {
            return true;
        }
        
        $isValid = resolve(EmailListVerifyValidatorHelper::class)->isValidEmail($this->leadContactEmail->email);
        if ($isValid === true) {
            $this->leadContactEmail->is_valid = true;
            $validationStatus = $this->validStatusCode;
        }
        if ($isValid === false) {
            $this->leadContactEmail->is_valid = false;
            $validationStatus = $this->invalidStatusCode;
        }
        // Si es null, dejo "leadContactEmail->is_valid" como estaba.
        if ($isValid === null) {
            $validationStatus = $this->nullStatusCode;
        }
        
        $this->leadContactEmail->setEmailListVerifyValidationStatus($validationStatus);
        $this->leadContactEmail->saveOrFail();
        return true;
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
