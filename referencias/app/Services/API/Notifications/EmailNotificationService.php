<?php

namespace App\Services\API\Notifications;

use DateTime;
use Throwable;
use Exception;
use Carbon\Carbon;
use App\Models\Email;
use Illuminate\Support\Str;
use App\Services\API\LeadService;
use App\Services\API\EmailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\EmailNotificationLog;
use App\Services\API\UserNotificationService;
use App\Services\API\LeadContactEmailService;
use App\Services\API\LeadNotificationEmailService;
use App\Repositories\EmailNotificationLogRepository;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;
use App\DTO\Notifications\Mailer\SentEmailNotificationDTO;
use App\DTO\Notifications\Mailer\OpenedEmailNotificationDTO;
use App\DTO\Notifications\Mailer\BouncedEmailNotificationDTO;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\DTO\Notifications\Mailer\SentQuickEmailNotificationDTO;
use App\DTO\Notifications\Mailer\ComplainedEmailNotificationDTO;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\DTO\Notifications\Mailer\UnsubscribedEmailNotificationDTO;


class EmailNotificationService
{

    protected $leadService;
    protected $emailService;
    protected $browserEventsDispatcher;
    protected $leadContactEmailService;
    protected $leadNotificationEmailService;
    protected $emailEventsDispatcherService;
    protected $emailNotificationLogRepository;
    protected $timelineEventsDispatcherService;


    public function __construct(
        EmailNotificationLogRepository $emailNotificationLogRepository,
        EmailService $emailService,
        LeadService $leadService,
        LeadNotificationEmailService $leadNotificationEmailService,
        LeadContactEmailService $leadContactEmailService,
        BrowserEventsDispatcher $browserEventsDispatcher,
        EmailEventsDispatcherService $emailEventsDispatcherService,
        TimelineEventsDispatcherService $timelineEventsDispatcherService,
        UserNotificationService $userNotificationService
    ) {
        $this->leadService = $leadService;
        $this->emailService = $emailService;
        $this->browserEventsDispatcher = $browserEventsDispatcher;
        $this->leadContactEmailService = $leadContactEmailService;
        $this->userNotificationService = $userNotificationService;
        $this->leadNotificationEmailService = $leadNotificationEmailService;
        $this->emailEventsDispatcherService = $emailEventsDispatcherService;
        $this->emailNotificationLogRepository = $emailNotificationLogRepository;
        $this->timelineEventsDispatcherService = $timelineEventsDispatcherService;
    }


    public function handleSentEmailNotification(SentEmailNotificationDTO $dto): ?Email
    {
        $sentEmail = $this->emailService->findOneByExternalIdAndExternalCustomId(
            $dto->id, $dto->appCustomId
        );
        if (!$sentEmail || !$sentEmail->lead || $sentEmail->sent_date) {
            return null;
        }
        $sentEmail->sent_date = $dto->sentAt;
        $sentEmail->saveOrFail();

        $this->emailEventsDispatcherService->dispatchEmailSentJob($sentEmail);
        $this->timelineEventsDispatcherService->setLoginUser($sentEmail->user)->leadEmailSent($sentEmail);
        return $sentEmail->fresh();
    }


    public function handleSentQuickEmailNotification(SentQuickEmailNotificationDTO $dto): void
    {
        $isEmailOfNewLead = Str::startsWith($dto->appCustomId, 'SYSTEM_new_lead_');
        if ($isEmailOfNewLead) {
            $leadId = $dto->appCustomMetadata['lead']['id'];
            $lead = $this->leadService->find($leadId);
            if ($lead->leadNotificationEmail) {
                $externalEmailId = $dto->id;
                $sentDate = new DateTime($dto->sentAt);

                $this->leadNotificationEmailService->markAsSent(
                    $lead->leadNotificationEmail, $sentDate, $externalEmailId
                );
            }
        }

        $isLeadUserChangeNotifEmail = Str::startsWith($dto->appCustomId, 'SYSTEM_lead_user_change_');
        if ($isLeadUserChangeNotifEmail) {
            $leadNotificationEmailId = $dto->appCustomMetadata['leadNotificationEmail']['id'] ?? null;
            if (!$leadNotificationEmailId) {
                return;
            }
            $externalEmailId = $dto->id;
            $sentDate = new DateTime($dto->sentAt);
            $leadNotificationEmail = $this->leadNotificationEmailService->findOrFail($leadNotificationEmailId);
            $this->leadNotificationEmailService->markAsSent(
                $leadNotificationEmail, $sentDate, $externalEmailId
            );
        }

        $isEmailOfGroupedNewLeads = $dto->appCustomId === 'SYSTEM_GROUPED_new_leads';
        if ($isEmailOfGroupedNewLeads) {
            $leadIds = collect($dto->appCustomMetadata['lead']['id']);
            $leads = $this->leadService->findByIds($leadIds);
            $externalEmailId = $dto->id;
            $sentDate = new DateTime($dto->sentAt);
            $leadNotificationEmails = $leads->map(function ($lead) {
                return $lead->leadNotificationEmail;
            });
            $leadNotificationEmails = $leadNotificationEmails->filter();
            if ($leadNotificationEmails->isNotEmpty()) {
                $this->leadNotificationEmailService->markMultipleAsSent(
                    $leadNotificationEmails, $sentDate, $externalEmailId
                );
            }
        }

        $isUserNotificationEmail = Str::startsWith($dto->appCustomId, 'SYSTEM_new_user_notification_');
        if ($isUserNotificationEmail) {
            $userNotificationId = $dto->appCustomMetadata['id'];
            $sentDate = new DateTime($dto->sentAt);
            $this->userNotificationService->markAsSent($userNotificationId, $sentDate);
        }
    }


    public function handleOpenedEmailNotification(OpenedEmailNotificationDTO $dto, array $opts = []): ?Email
    {
        $openedEmail = $this->emailService->findOneByExternalIdAndExternalCustomId(
            $dto->id, $dto->appCustomId
        );
        if (!$openedEmail) {
            return null;
        }
        
        $discardIfAlreadyOpened = $opts['discardIfAlreadyOpened'] ?? false;

        if ($openedEmail->opened_date) {
            if (!$discardIfAlreadyOpened) {
                $this->persistNewOpenedNotificationEvent($openedEmail);
                $this->emailEventsDispatcherService->dispatchEmailReOpenedJob($openedEmail);
            }
            return $openedEmail;
        }

        try {
            DB::beginTransaction();
            $this->persistNewOpenedNotificationEvent($openedEmail);
            $openedEmail->opened_date = $dto->openedAt;
            $openedEmail->saveOrFail();
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->timelineEventsDispatcherService->setLoginUser($openedEmail->user)->leadEmailOpened($openedEmail);
        $this->emailEventsDispatcherService->dispatchEmailOpenedJob($openedEmail);
        if ($openedEmail->is_proposal) {
            $this->browserEventsDispatcher->notifyOpenedProposal($openedEmail);
        }
        return $openedEmail->fresh();
    }


    public function handleBouncedEmailNotification(BouncedEmailNotificationDTO $dto): ?Email
    {
        $bouncedEmail = $this->emailService->findOneByExternalIdAndExternalCustomId(
            $dto->id, $dto->appCustomId
        );
        if (!$bouncedEmail || $bouncedEmail->bounced_date) {
            return null;
        }
        $leadContactEmails = $this->leadContactEmailService->findByClientAndEmail(
            $bouncedEmail->client, $bouncedEmail->leadContactEmail->email
        );
        if ($leadContactEmails->isEmpty()) {
            return null;
        }

        try {
            DB::beginTransaction();
            $this->persistNewBouncedNotificationEvent($bouncedEmail, $leadContactEmails);
            // $this->leadContactEmailService->markAsBounced($leadContactEmails);
            $bouncedEmail->bounced_date = new DateTime('now');
            $bouncedEmail->saveOrFail();
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->emailEventsDispatcherService->dispatchMarkLeadContactEmailsAsBouncedJob($leadContactEmails);
        return $bouncedEmail->fresh();
    }


    public function handleComplainedEmailNotification(ComplainedEmailNotificationDTO $dto): ?Email
    {
        $complainedEmail = $this->emailService->findOneByExternalIdAndExternalCustomId(
            $dto->id, $dto->appCustomId
        );
        if (!$complainedEmail || $complainedEmail->complained_date) {
            return null;
        }
        $leadContactEmails = $this->leadContactEmailService->findByClientAndEmail(
            $complainedEmail->client, $complainedEmail->leadContactEmail->email
        );
        if ($leadContactEmails->isEmpty()) {
            return null;
        }

        try {
            DB::beginTransaction();
            $this->persistNewComplainedNotificationEvent($complainedEmail, $leadContactEmails);
            // $this->leadContactEmailService->markAsComplained($leadContactEmails);
            $complainedEmail->complained_date = new DateTime('now');
            $complainedEmail->saveOrFail();
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        
        $this->emailEventsDispatcherService->dispatchMarkLeadContactEmailsAsComplainedJob($leadContactEmails);
        return $complainedEmail->fresh();
    }


    public function handleUnsubscribedEmailNotification(UnsubscribedEmailNotificationDTO $dto): ?Email
    {
        $unsubscribedEmail = $this->emailService->findOneByExternalIdAndExternalCustomId(
            $dto->id, $dto->appCustomId
        );
        if (!$unsubscribedEmail || $unsubscribedEmail->unsubscribed_date) {
            return null;
        }
        $leadContactEmails = $this->leadContactEmailService->findByClientAndEmail(
            $unsubscribedEmail->client, $unsubscribedEmail->leadContactEmail->email
        );
        if ($leadContactEmails->isEmpty()) {
            return null;
        }

        try {
            DB::beginTransaction();
            $this->persistNewUnsubscribedNotificationEvent($unsubscribedEmail, $leadContactEmails);
            // $this->leadContactEmailService->markAsUnsubscribed($leadContactEmails);
            $unsubscribedEmail->unsubscribed_date = new DateTime('now');
            $unsubscribedEmail->saveOrFail();
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->emailEventsDispatcherService->dispatchMarkLeadContactEmailsAsUnsubscribedJob($leadContactEmails);
        return $unsubscribedEmail->fresh();
    }


    public function findLastOpenLogWithReopenedProposalEmailNotification(Email $email): ?EmailNotificationLog
    {
        return $this->emailNotificationLogRepository->findLastOpenLogWithReopenedProposalEmailNotification($email);
    }


    public function findLastOpenLogWithReopenedProposalBrowserNotification(Email $email): ?EmailNotificationLog
    {
        return $this->emailNotificationLogRepository->findLastOpenLogWithReopenedProposalBrowserNotification(
            $email
        );
    }


    public function findLastOpenLog(Email $email): ?EmailNotificationLog
    {
        return $this->emailNotificationLogRepository->findLastOpenLog($email);
    }


    public function markLastOpenLogWithReopenedProposalEmailNotified(Email $email): ?EmailNotificationLog
    {
        $lastLog = $this->findLastOpenLog($email);
        if (!$lastLog) {
            return $lastLog;
        }
        if ($lastLog->reopened_proposal_email_notification_date) {
            return $lastLog;
        }
        $lastLog = $this->emailNotificationLogRepository->markLogWithReopenedProposalEmailNotified($lastLog);
        return $lastLog;
    }


    public function markLastOpenLogWithReopenedProposalBrowserNotified(Email $email): ?EmailNotificationLog
    {
        $lastLog = $this->findLastOpenLog($email);
        if (!$lastLog) {
            return $lastLog;
        }
        if ($lastLog->reopened_proposal_browser_notification_date) {
            return $lastLog;
        }
        $lastLog = $this->emailNotificationLogRepository->markLogWithReopenedProposalBrowserNotified($lastLog);
        return $lastLog;
    }


    protected function persistNewOpenedNotificationEvent(Email $email): EmailNotificationLog
    {
        return $this->emailNotificationLogRepository->create([
            'email_id' => $email->id,
            'client_id' => $email->client_id,
            'event' => EmailNotificationLog::OPEN_EVENT,
            'affected_lead_contact_email_ids' => [$email->leadContactEmail->id],
        ]);
    }


    protected function persistNewBouncedNotificationEvent(
        Email $email,
        Collection $leadContactEmails
    ): EmailNotificationLog {
        return $this->emailNotificationLogRepository->create([
            'email_id' => $email->id,
            'client_id' => $email->client_id,
            'event' => EmailNotificationLog::BOUNCE_EVENT,
            'affected_lead_contact_email_ids' => $leadContactEmails->pluck('id'),
        ]);
    }


    protected function persistNewComplainedNotificationEvent(
        Email $email,
        Collection $leadContactEmails
    ): EmailNotificationLog {
        return $this->emailNotificationLogRepository->create([
            'email_id' => $email->id,
            'client_id' => $email->client_id,
            'event' => EmailNotificationLog::COMPLAINT_EVENT,
            'affected_lead_contact_email_ids' => $leadContactEmails->pluck('id'),
        ]);
    }


    protected function persistNewUnsubscribedNotificationEvent(
        Email $email,
        Collection $leadContactEmails
    ): EmailNotificationLog {
        return $this->emailNotificationLogRepository->create([
            'email_id' => $email->id,
            'client_id' => $email->client_id,
            'event' => EmailNotificationLog::UNSUBSCRIBE_EVENT,
            'affected_lead_contact_email_ids' => $leadContactEmails->pluck('id'),
        ]);
    }


}
