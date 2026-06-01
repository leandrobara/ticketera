<?php

namespace App\Services\API\Automations;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\Lead;
use App\Models\Email;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Models\AutomationLog;
use App\Services\API\UserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\AutomationProposal;
use App\Services\API\EmailService;
use App\Services\API\AttachmentService;
use App\DTO\EmailScheduleParametersDTO;
use App\Models\AutomationProposalResendRule;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\Automations\AutomationProposalResendDTO;
use App\Services\API\Notifications\NotificationService;
use App\Repositories\Automations\AutomationProposalResendRuleRepository;
use App\Exceptions\Services\Automations\AutomationProposalResendServiceException;
use App\Exceptions\Services\EmailService\EmailSendValidationUserNotEnabledException;


class AutomationProposalResendService
{

    use GetClientFromRequest;


    public function __construct(
        private readonly AutomationProposalResendRuleRepository $automationProposalResendRuleRepository,
        private readonly AutomationLogService $automationLogService,
        private readonly UserService $userService,
        private readonly EmailService $emailService,
        private readonly AttachmentService $attachmentService,
        private readonly NotificationService $notificationService,
        private readonly int $windowHoursLimit,
    ) {
    }


    public function findByAutomationProposal(AutomationProposal $automationProposal): ?AutomationProposalResendRule
    {
        return $this->automationProposalResendRuleRepository->findByAutomationProposal($automationProposal);
    }


    public function findRuleByClient(Client $client): ?AutomationProposalResendRule
    {
        return $this->automationProposalResendRuleRepository->findRuleByClient($client);
    }


    public function save(AutomationProposalResendDTO $dto): ?AutomationProposalResendRule
    {
        $rule = null;
        if ($dto->sendEmailTemplate && is_numeric($dto->sendDelayDays) && $dto->sendHour) {
            $rule = $this->automationProposalResendRuleRepository->findRuleByClient($this->getClient());
            if (!$rule) {
                $rule = $this->automationProposalResendRuleRepository->create($dto);
                return $rule;
            }
            if (!$this->parametersChanged($rule, $dto)) {
                return $rule;
            }

            // If rule was never applied, I can update the row.
            $lastAppliedLog = $this->automationLogService->findLastOneByAutomationProposalResendRule($rule);
            if (!$lastAppliedLog) {
                $rule = $this->automationProposalResendRuleRepository->update($rule, $dto);
                return $rule;
            }
            try {
                DB::beginTransaction();
                $this->automationProposalResendRuleRepository->delete($rule);
                $rule = $this->automationProposalResendRuleRepository->create($dto);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $serviceException = new AutomationProposalResendServiceException(
                    $e->getMessage(), (int) $e->getCode()
                );
                throw $serviceException;
            }
        }
        return $rule;
    }


    public function parametersChanged(
        AutomationProposalResendRule $rule,
        AutomationProposalResendDTO $dto
    ): bool {
        $cancellingTags = $rule->cancellingTags->pluck('id')->toArray();
        $cancellingStatus = $rule->cancellingStatusList->pluck('id')->toArray();
        if (
            $cancellingTags === $dto->cancellingTags->pluck('id')->toArray() &&
            $cancellingStatus === $dto->cancellingStatus->pluck('id')->toArray() &&
            $rule->send_email_template_id === $dto->sendEmailTemplate->id &&
            $rule->add_original_attachments === $dto->addOriginalAttachments &&
            $rule->cancel_if_proposal_was_opened === $dto->cancelIfProposalWasOpened &&
            $rule->cancel_if_proposal_was_already_sent === $dto->cancelIfProposalWasAlreadySent &&
            $rule->cancelling_enabled === $dto->cancellingEnabled &&
            $rule->enabled === $dto->enabled &&
            $rule->send_delay_days === $dto->sendDelayDays &&
            $rule->send_hour === $dto->sendHour
        ) {
            return false;
        }
        return true;
    }


    public function findSentProposals(AutomationProposalResendRule $rule): Collection
    {
        $client = $rule->client;
        $dateEnd = $this->getEndDateToSearchProposals($rule);
        $dateStart = $this->getStartDateToSearchProposals($rule);
        $emails = $this->emailService->findProposalsBetweenSentDatesByClient($client, $dateStart, $dateEnd);
        return $emails;
    }


    public function apply(Email $originalEmail): ?AutomationLog
    {
        if (
            !$originalEmail->lead ||
            !$originalEmail->sent_date ||
            !$originalEmail->is_proposal ||
            $originalEmail->automation_log_id
        ) {
            return null;
        }

        $rule = $this->automationProposalResendRuleRepository->findEnabledRuleByClient($originalEmail->client);
        if (!$rule) {
            return null;
        }
        if (!$this->isTimeToSend($originalEmail->sent_date, $rule)) {
            return null;
        }

        $automation = $rule->automationProposal;
        if (!$automation || !$automation->enabled) {
            return null;
        }

        $existentLog = $this->findExistentLog($originalEmail->lead, $originalEmail, $rule);
        if ($existentLog) {
            return null;
        }

        if (!$this->isEligible($rule, $originalEmail)) {
            try {
                DB::beginTransaction();
                $automationLog = $this->storeAutomationLog($applied = false, $originalEmail, $rule);
                if (!$this->isLeadUserVerifiedToSendEmails($originalEmail->lead)) {
                    $this->notificationService->storeAutomationEmailSendingEmailError($automationLog);
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            return $automationLog;
        }

        $automationLog = $this->storeAutomationLog($applied = true, $originalEmail, $rule);
        $this->sendEmailToLead($originalEmail, $rule, $automationLog);
        return $automationLog->fresh();
    }


    public function sendEmailToLead(
        Email $originalEmail,
        AutomationProposalResendRule $rule,
        AutomationLog $automationLog
    ): Collection {
        try {
            DB::beginTransaction();
            $originalEmail = $this->emailService->fillEmailWithMailerInfo(
                $originalEmail, ['id', 'body', 'attachments']
            );

            $emailScheduleParamsDTO = $this->buildEmailScheduleParamsDTO(
                $originalEmail, $rule, $automationLog
            );
            $this->emailService->setRequestUser($originalEmail->user);
            $emails = $this->emailService->scheduleToLead($originalEmail->lead, $emailScheduleParamsDTO);

            DB::commit();
        } catch (EmailSendValidationUserNotEnabledException $e) {
            report($e);
            DB::rollBack();
            $this->markAutomationLogAsNotApplied($automationLog);
            $this->notificationService->storeAutomationEmailSendingEmailError($automationLog);
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $emails ?? (new Collection());
    }


    public function buildEmailScheduleParamsDTO(
        Email $originalEmail,
        AutomationProposalResendRule $rule,
        AutomationLog $automationLog
    ): EmailScheduleParametersDTO {
        $attachments = $this->getAllAttachments($originalEmail, $rule);
        $body = $this->addLastProposalBodyAndSign($rule->sendEmailTemplate->body, $originalEmail);

        $scheduleParamsDTO = new EmailScheduleParametersDTO();
        $scheduleParamsDTO->body = $body;
        $scheduleParamsDTO->attachments = $attachments;
        $scheduleParamsDTO->automationLog = $automationLog;
        $scheduleParamsDTO->subject = $rule->sendEmailTemplate->subject;
        $scheduleParamsDTO->isProposal = $rule->sendEmailTemplate->is_proposal;
        $scheduleParamsDTO->sendDate = (new DateTime())->format('Y-m-d\TH:i:sP');
        return $scheduleParamsDTO;
    }


    public function storeAutomationLog(bool $applied, Email $email, AutomationProposalResendRule $rule): AutomationLog
    {
        return $this->automationLogService->createAutomationProposalResendLog($applied, $email, $rule);
    }


    public function isEligible(AutomationProposalResendRule $rule, Email $email): bool
    {
        $lead = $email->lead;
        $leadContactEmails = $lead->leadContactEmails->filter(function ($lce) {
            return !$lce->unsubscribed && !$lce->complained && !$lce->bounced && $lce->is_valid;
        });
        if ($leadContactEmails->isEmpty()) {
            return false;
        }

        if (!$this->isLeadUserVerifiedToSendEmails($lead)) {
            return false;
        }
        if ($this->leadProposalWasAlreadySent($lead, $rule)) {
            return false;
        }
        if ($this->leadProposalWasOpened($email, $rule)) {
            return false;
        }
        //cancelling is enabled?
        if ($rule->cancelling_enabled) {
            if ($this->leadHasCancellingStatus($lead, $rule)) {
                return false;
            }
            if ($this->leadHasCancellingTag($lead, $rule)) {
                return false;
            }
        }
        if (!$rule->sendEmailTemplate) {
            return false;
        }
        return true;
    }


    public function leadProposalWasAlreadySent(Lead $lead, AutomationProposalResendRule $rule)
    {
        if ($rule->cancel_if_proposal_was_already_sent) {
            $filters = ['is_proposal' => true, 'automation_log_id' => null];
            return $this->emailService->findFilteredEmailsByLead($lead, $filters)->count() > 1;
        }
        return false;
    }


    public function leadProposalWasOpened(Email $email, AutomationProposalResendRule $rule)
    {
        if ($rule->cancel_if_proposal_was_opened) {
            return $email->opened_date ? true : false;
        }
        return false;
    }


    public function findExistentLog(Lead $lead, Email $email, AutomationProposalResendRule $rule): ?AutomationLog
    {
        return $this
            ->automationLogService
            ->findOneByLeadAndEmailAndAutomationProposalResendRule($lead, $email, $rule)
        ;
    }


    public function leadHasCancellingStatus(Lead $lead, AutomationProposalResendRule $rule): bool
    {
        return $rule->cancellingStatusList->contains($lead->status);
    }


    public function leadHasCancellingTag(Lead $lead, AutomationProposalResendRule $rule): bool
    {
        return $rule->cancellingTags->intersect($lead->tags)->isNotEmpty();
    }


    public function isTimeToSend(DateTime $originalSentDatetime, AutomationProposalResendRule $rule): bool
    {
        $now = $this->getDateNow();
        $sendDatetime = $this->getDateSend($originalSentDatetime, $rule);
        
        $sendTimeIsReached = $now >= $sendDatetime;
        $limitWindowIsExceeded = $this->isExceededLimitDate($sendDatetime);

        if ($sendTimeIsReached && !$limitWindowIsExceeded) {
            return true;
        }
        return false;
    }


    public function isInHourToApply(AutomationProposalResendRule $rule): bool
    {
        $dateNow = $this->getDateNow();
        $hourNow = (int) $dateNow->format('H');
        $hourArr = explode(':', $rule->send_hour);
        $hourToApply = (int) $hourArr[0];
        $hoursAbsoluteDiff = absoluteHoursDiff($hourNow, $hourToApply);
        if ($hoursAbsoluteDiff <= $this->windowHoursLimit) {
            return true;
        }
        return false;
    }


    // PRECONDICIÓN: DATETIME_ACTUAL es MAYOR a $automationSendDate
    public function isExceededLimitDate(DateTime $automationSendDate): bool
    {
        $now = $this->getDateNow();
        $sendDatePlusWindowHours = (clone $automationSendDate)->modify("+{$this->windowHoursLimit} hours");

        return $now >= $sendDatePlusWindowHours;
    }


    private function getDateSend(DateTime $originalSentDateTime, AutomationProposalResendRule $rule): DateTime
    {
        $delayDays = $rule->send_delay_days;
        $clientTz = new DateTimeZone($rule->client->timezone);
        $clientSendDateTime = (clone $originalSentDateTime)->setTimezone($clientTz);
        // Para sumar días, dejo la fecha con el TZ del cliente para evitar desfasajes.
        $sendDateTime = (clone $clientSendDateTime)->modify('+ ' . $delayDays . ' days');
        // Seteo una fecha con el TZ UTC0, y luego le asigno la hora.
        $sendDateTime = (new Datetime('now'))->setDate(
            (int) $sendDateTime->format('Y'), (int) $sendDateTime->format('m'), (int) $sendDateTime->format('d')
        );
        $hourArr = explode(':', $rule->send_hour);
        if ($hourArr) {
            $sendDateTime->setTime($hourArr[0], $hourArr[1]);
        } else {
            $sendDateTime->setTime(
                (int) $originalSentDateTime->format('H'), (int) $originalSentDateTime->format('i')
            );
        }
        return $sendDateTime;
    }


    private function getStartDateToSearchProposals(AutomationProposalResendRule $resendRule): DateTime
    {
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($resendRule->client->timezone);
        $dateTime->setTimezone($clientTz);
        // Que hora es para el sistema cuando para el cliente son las 00:00
        $dateTime->modify("- {$resendRule->send_delay_days} days")->setTime(0, 0, 0)->setTimezone($systemTz);
        return $dateTime;
    }


    private function getEndDateToSearchProposals(AutomationProposalResendRule $resendRule): DateTime
    {
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($resendRule->client->timezone);
        $dateTime->setTimezone($clientTz);
        // Que hora es para el sistema cuando para el cliente son las 23:59:59
        $dateTime->modify("- {$resendRule->send_delay_days} days")->setTime(23, 59, 59)->setTimezone($systemTz);
        return $dateTime;
    }


    private function getAllAttachments(Email $originalEmail, AutomationProposalResendRule $rule): Collection
    {
        $attachments = $rule->sendEmailTemplate->attachments;
        if (!$rule->add_original_attachments) {
            return $attachments;
        }

        $mailerAttachments = $originalEmail->getMailerDTO()->get('attachments');
        foreach ($mailerAttachments as $mailerAttachment) {
            $originalEmailAttachment = $this->attachmentService->findOneByClientIdAndHashAndName(
                $originalEmail->client_id, $mailerAttachment['hash'], $mailerAttachment['name']
            );
            if ($originalEmailAttachment) {
                $attachments->push($originalEmailAttachment);
            }
        }
        return $attachments->unique();
    }


    private function addLastProposalBodyAndSign(string $body, Email $originalEmail): string
    {
        $newBody = $body;
        $emailSign = $this->userService->getEmailSign($originalEmail->user);
        if ($emailSign) {
            $newBody = $newBody . $emailSign;
        }
        $lastProposalBody = $this->getEmailBody($originalEmail);
        $propSeparator = config('emails.proposal_resend_separator');
        $propSeparatorEnd = config('emails.proposal_resend_end_separator_flag');
        $propSeparatorStart = config('emails.proposal_resend_start_separator_flag');

        $newBody = $newBody . $propSeparatorStart . $propSeparator . $lastProposalBody . $propSeparatorEnd;

        return $newBody;
    }


    private function getEmailBody(Email $email): string
    {
        $body = $email->getMailerDTO()->get('body');
        $signStart = config('emails.email_sign_start_separator_flag');
        $body = Str::before($body, $signStart);
        return $body;
    }


    private function isLeadUserVerifiedToSendEmails(Lead $lead): bool
    {
        $user = $lead->user;
        return ($user->email_is_verified && $user->email_from_address && $user->email_from_name);
    }


    private function markAutomationLogAsNotApplied(AutomationLog $automationLog): AutomationLog
    {
        return $this->automationLogService->markAsNotApplied($automationLog);
    }


    // Unifico esto acá para poder hacer pruebas cambiando la "fecha actual"
    private function getDateNow(): DateTime
    {
        // return environmentIsNotProduction() ? new DateTime('2022-11-09 15:00:00') : new DateTime('now');
        return new DateTime('now');
    }

}
