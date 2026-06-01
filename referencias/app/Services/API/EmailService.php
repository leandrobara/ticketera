<?php

namespace App\Services\API;

use DateTime;
use Exception;
use Throwable;
use App\Models\User;
use App\Models\Lead;
use App\Models\Email;
use App\Models\Client;
use Illuminate\Support\Str;
use App\DTO\MailerEmailDTO;
use App\Models\ClientSettings;
use App\Models\LeadContactEmail;
use Illuminate\Support\Facades\DB;
use App\DTO\MailerSendResponseDTO;
use Illuminate\Support\Collection;
use App\DTO\EmailSendParametersDTO;
use App\Repositories\EmailRepository;
use App\DTO\MailerScheduleResponseDTO;
use App\DTO\EmailScheduleParametersDTO;
use App\Helpers\ClientyMailerAPIHelper;
use App\DTO\EmailMassiveSendParametersDTO;
use App\Services\Traits\GetUserFromRequest;
use App\DTO\MailerSendRequestParametersDTO;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\EmailSystemScheduleParametersDTO;
use App\DTO\MailerMassiveScheduleResponseDTO;
use App\DTO\EmailMassiveScheduleParametersDTO;
use App\DTO\MailerScheduleRequestParametersDTO;
use App\Services\Validators\EmailServiceValidator;
use App\DTO\MailerMassiveScheduleRequestParametersDTO;
use App\DTO\Notifications\Mailer\SentEmailNotificationDTO;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;


class EmailService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $validator;
    private $systemNameFrom;
    private $emailRepository;
    private $systemEmailFrom;
    private $clientyMailerAPIHelper;
    private $emailEventsDispatcherService;
    private $clientEventsDispatcherService;
    private $timelineEventsDispatcherService;


    public function __construct(
        EmailRepository $emailRepository,
        EmailServiceValidator $validator,
        ClientyMailerAPIHelper $clientyMailerAPIHelper,
        EmailEventsDispatcherService $emailEventsDispatcherService,
        ClientEventsDispatcherService $clientEventsDispatcherService,
        TimelineEventsDispatcherService $timelineEventsDispatcherService,
        string $systemEmailFrom,
        string $systemNameFrom
    ) {
        $this->validator = $validator;
        $this->systemNameFrom = $systemNameFrom;
        $this->emailRepository = $emailRepository;
        $this->systemEmailFrom = $systemEmailFrom;
        $this->clientyMailerAPIHelper = $clientyMailerAPIHelper;
        $this->emailEventsDispatcherService = $emailEventsDispatcherService;
        $this->clientEventsDispatcherService = $clientEventsDispatcherService;
        $this->timelineEventsDispatcherService = $timelineEventsDispatcherService;
    }


    public function findFilteredEmailsByLead(Lead $lead, array $filters = []): Collection
    {
        return $this->emailRepository->findFilteredEmailsByLead($lead, $filters);
    }


    public function findLastOneSentByUser(User $user): ?Email
    {
        return $this->emailRepository->findLastOneSentByUser($user);
    }


    public function findByIdsAndClient(array $emailIds, Client $client): Collection
    {
        $emails = $this->emailRepository->findByIdsAndClient($emailIds, $client);
        return $emails;
    }


    public function cancelMassiveEmail(string $externalMassiveId): Collection
    {
        try {
            DB::beginTransaction();
            $cancelledExternalEmailIds = $this->clientyMailerAPIHelper->cancelMassiveEmail($externalMassiveId);
            $cancelledExternalEmailIds = $cancelledExternalEmailIds->toArray();
            $cancelledEmailIds = $this->emailRepository->cancelMassiveEmailByExternalIds($cancelledExternalEmailIds);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }


        $this->timelineEventsDispatcherService->massiveEmailCancelled($this->getUser(), $cancelledExternalEmailIds);

        return $cancelledEmailIds;
    }


    public function cancelEmails(Collection $emails): Collection
    {
        $this->clientyMailerAPIHelper->cancelEmails($emails);

        try {
            DB::beginTransaction();
            $cancelledEmails = $this->emailRepository->cancelEmails($emails);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $cancelledExternalEmailIds = $cancelledEmails->pluck('external_id')->toArray();
        $this->timelineEventsDispatcherService->massiveEmailCancelled($this->getUser(), $cancelledExternalEmailIds);

        return $cancelledEmails;
    }


    public function findOneByExternalIdAndExternalCustomId(int $externalId, string $externalCustomId): ?Email
    {
        return $this->emailRepository->findOneByExternalIdAndExternalCustomId($externalId, $externalCustomId);
    }


    public function findOneOrFailByExternalId(int $externalId): ?Email
    {
        return $this->emailRepository->findOneOrFailByExternalId($externalId);
    }


    public function find(int $id): Email
    {
        return Email::findOrFail($id);
    }


    public function findAlreadyManuallySentProposalsByLeadCollection(Collection $leads)
    {
        return $this->emailRepository->findAlreadyManuallySentProposalsByLeadCollection($leads);
    }


    public function findProposalsBetweenSentDatesByClient(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd
    ): Collection {
        return $this->emailRepository->findProposalsBetweenSentDatesByClient($client, $dateStart, $dateEnd);
    }


    public function sendToLead(Lead $lead, EmailSendParametersDTO $sendDTO): Collection
    {
        $this->validator->validateUserEmailSendingEnabled($this->getUser());

        $sendDTO->individualLeadSendHash = str_replace('-', '', Str::orderedUuid());
        $this->validator->validateEmailSendToLeadParameters($sendDTO);

        $sentEmails = collect([]);

        try {
            DB::beginTransaction();
            foreach ($sendDTO->leadContactEmails as $leadContactEmail) {
                $mailerSendResponseDTO = $this->sendMailerEmailNow($leadContactEmail, $sendDTO);
                $email = $this->storeSentEmail($sendDTO, $leadContactEmail, $mailerSendResponseDTO);
                $sentEmails->push($email);

                // Si tiene Cc, los envío solamente en el primer email enviado, para no mandar varios cc iguales.
                $sendDTO->cc = null;
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        foreach ($sentEmails as $email) {
            $this->timelineEventsDispatcherService->leadEmailSent($email);
            $this->emailEventsDispatcherService->dispatchEmailSentJob($email);
            // $this->clientEventsDispatcherService->dispatchSaveClientInteractionJob(
            //     $this->getRequestClientOrNull()
            // );
        }

        return $sentEmails;
    }


    public function scheduleToLead(
        Lead $lead,
        EmailScheduleParametersDTO $scheduleDTO
    ): Collection {
        $scheduledEmails = collect([]);
        $scheduleDTO->individualLeadSendHash = str_replace('-', '', Str::orderedUuid());

        $leadContactEmails = $scheduleDTO->leadContactEmails && $scheduleDTO->leadContactEmails->isNotEmpty()
            ? $scheduleDTO->leadContactEmails
            : $lead->leadContactEmails
        ;
        $leadContactEmails = $leadContactEmails->filter(function ($lce) {
            return !$lce->unsubscribed && !$lce->complained && !$lce->bounced && $lce->is_valid;
        });
        foreach ($leadContactEmails as $leadContactEmail) {
            $email = $this->scheduleToLeadContactEmail($leadContactEmail, $scheduleDTO);
            $scheduledEmails->push($email);

            // Si tiene Cc, los envío solamente en el primer email enviado, para no mandar varios cc iguales.
            $scheduleDTO->cc = null;
        }

        return $scheduledEmails;
    }


    public function sendToLeadContactEmail(
        LeadContactEmail $leadContactEmail,
        EmailSendParametersDTO $sendParametersDTO
    ): Email {
        $this->validator->validateUserEmailSendingEnabled($this->getUser());
        $this->validator->validateEmailSendParameters($sendParametersDTO);

        try {
            DB::beginTransaction();
            $mailerSendResponseDTO = $this->sendMailerEmailNow($leadContactEmail, $sendParametersDTO);
            $email = $this->storeSentEmail($sendParametersDTO, $leadContactEmail, $mailerSendResponseDTO);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        
        $this->timelineEventsDispatcherService->leadEmailSent($email);
        $this->emailEventsDispatcherService->dispatchEmailSentJob($email);
        return $email;
    }


    public function scheduleToLeadContactEmail(
        LeadContactEmail $leadContactEmail,
        EmailScheduleParametersDTO $scheduleParametersDTO
    ): Email {
        $this->validator->validateUserEmailSendingEnabled($this->getUser());
        $this->validator->validateEmailScheduleParameters($scheduleParametersDTO);
        try {
            DB::beginTransaction();
            $mailerScheduleResponseDTO = $this->scheduleMailerEmail($leadContactEmail, $scheduleParametersDTO);
            $email = $this->storeScheduledEmail(
                $scheduleParametersDTO, $leadContactEmail, $mailerScheduleResponseDTO
            );
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->timelineEventsDispatcherService->setLoginUser($email->user)->leadEmailScheduled($email);
        return $email;
    }


    public function scheduleSystemEmail(
        string $emailTo,
        EmailSystemScheduleParametersDTO $scheduleParamsDTO
    ): MailerScheduleResponseDTO {
        $this->validator->validateSystemEmailScheduleParameters($scheduleParamsDTO);

        $user = $this->getUser();
        $client = $user->client;
        $defaultAppCustomId = 'SYS_CID_' . $client->id . '_UID_' . $user->id;

        $mailerDTO = new MailerScheduleRequestParametersDTO();
        $mailerDTO->to = $emailTo;
        $mailerDTO->hasOpenTracking = true;
        $mailerDTO->from = $this->systemEmailFrom;
        $mailerDTO->body = $scheduleParamsDTO->body;
        $mailerDTO->fromName = $this->systemNameFrom;
        $mailerDTO->subject = $scheduleParamsDTO->subject;
        $mailerDTO->sendDate = $scheduleParamsDTO->sendDate;
        $mailerDTO->appCustomMetadata = $scheduleParamsDTO->appCustomMetadata;
        $mailerDTO->appCustomId = $scheduleParamsDTO->mailerDTO ?? $defaultAppCustomId;

        try {
            if (redirectEmails()) {
                $mailerDTO->to = config('emails.redirect_emails_to');
            }

            $mailerSendResponseDTO = $this->clientyMailerAPIHelper->scheduleEmail($mailerDTO);
            return $mailerSendResponseDTO;
        } catch (Exception $e) {
            throw $e;
        }
    }


    public function sendMassiveEmail(EmailMassiveSendParametersDTO $sendParametersDTO): Collection
    {
        $user = $this->getUser();
        $clientSettings = $user->client->clientSettings;

        $this->validator->validateUserEmailSendingEnabled($user);
        $this->validator->validateEmailMassiveSendParameters($sendParametersDTO);

        try {
            DB::beginTransaction();

            $scheduleParamsDTO = EmailMassiveScheduleParametersDTO::buildFromSendDTO($sendParametersDTO);
            $mailerResponseDTO = $this->scheduleMailerMassiveEmail($scheduleParamsDTO);
            $insertedExternalIds = $this->storeMassiveScheduledEmails($scheduleParamsDTO, $mailerResponseDTO);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->emailEventsDispatcherService->dispatchMassiveEmailSentOrScheduledJob(
            $this->getUser(), $insertedExternalIds->toArray(), 'send'
        );
        return $insertedExternalIds;
    }


    public function scheduleMassiveEmail(EmailMassiveScheduleParametersDTO $scheduleParamsDTO): Collection
    {
        $this->validator->validateUserEmailSendingEnabled($this->getUser());
        $this->validator->validateEmailMassiveScheduleParameters($scheduleParamsDTO);

        try {
            DB::beginTransaction();
            $mailerResponseDTO = $this->scheduleMailerMassiveEmail($scheduleParamsDTO);
            $insertedExternalIds = $this->storeMassiveScheduledEmails($scheduleParamsDTO, $mailerResponseDTO);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->emailEventsDispatcherService->dispatchMassiveEmailSentOrScheduledJob(
            $this->getUser(), $insertedExternalIds->toArray(), 'schedule'
        );
        return $insertedExternalIds;
    }


    protected function sendMailerEmailNow(
        LeadContactEmail $leadContactEmail,
        EmailSendParametersDTO $sendParametersDTO
    ): MailerSendResponseDTO {
        $paramsDTO = MailerSendRequestParametersDTO::build($this->getUser(), $leadContactEmail, $sendParametersDTO);

        if (redirectEmails()) {
            $paramsDTO->to = config('emails.redirect_emails_to');
        }
        $mailerSendResponseDTO = $this->clientyMailerAPIHelper->sendEmail($paramsDTO);
        return $mailerSendResponseDTO;
    }


    protected function scheduleMailerEmail(
        LeadContactEmail $leadContactEmail,
        EmailScheduleParametersDTO $scheduleParametersDTO
    ): MailerScheduleResponseDTO {
        $paramsDTO = MailerScheduleRequestParametersDTO::build(
            $this->getUser(),
            $leadContactEmail,
            $scheduleParametersDTO
        );

        if (redirectEmails()) {
            $paramsDTO->to = config('emails.redirect_emails_to');
        }
        $mailerSendResponseDTO = $this->clientyMailerAPIHelper->scheduleEmail($paramsDTO);
        return $mailerSendResponseDTO;
    }


    protected function scheduleMailerMassiveEmail(
        EmailMassiveScheduleParametersDTO $massiveScheduleDTO
    ): MailerMassiveScheduleResponseDTO {
        $paramsDTO = MailerMassiveScheduleRequestParametersDTO::build($this->getUser(), $massiveScheduleDTO);

        if (redirectEmails()) {
            foreach ($paramsDTO->massiveData as $i => $data) {
                $paramsDTO->massiveData[$i]['to'] = config('emails.redirect_emails_to');
            }
        }
        $mailerSendResponseDTO = $this->clientyMailerAPIHelper->scheduleMassiveEmail($paramsDTO);
        return $mailerSendResponseDTO;
    }


    protected function storeSentEmail(
        EmailSendParametersDTO $sendParametersDTO,
        LeadContactEmail $leadContactEmail,
        MailerSendResponseDTO $mailerSendDTO
    ): Email {
        $user = $this->getUser();
        $email = $this->emailRepository->storeSentEmail(
            $user,
            $sendParametersDTO,
            $leadContactEmail,
            $mailerSendDTO
        );
        return $email;
    }


    protected function storeScheduledEmail(
        EmailScheduleParametersDTO $scheduleParametersDTO,
        LeadContactEmail $leadContactEmail,
        MailerScheduleResponseDTO $mailerScheduleDTO
    ): Email {
        $user = $this->getUser();
        $email = $this->emailRepository->storeScheduledEmail(
            $user, $scheduleParametersDTO, $leadContactEmail, $mailerScheduleDTO
        );

        return $email;
    }


    protected function storeMassiveScheduledEmails(
        EmailMassiveScheduleParametersDTO $paramsDTO,
        MailerMassiveScheduleResponseDTO $mailerResponseDTO
    ): Collection {
        $insertedExternalIds = $this->emailRepository->storeMassiveScheduledEmail(
            $this->getUser(), $paramsDTO, $mailerResponseDTO
        );
        return $insertedExternalIds;
    }


    public function fillEmailWithMailerInfo(Email $email, array $fields = []): Email
    {
        if (!$fields) {
            $params['fields'] = ['id', 'subject', 'opened_at', 'bounced_at', 'complained_at', 'unsubscribed_at'];
        } else {
            $params['fields'] = $fields;
        }
        $mailerData = $this->clientyMailerAPIHelper->getSentEmail($email, $params);
        $dto = MailerEmailDTO::buildFromEmail($mailerData);
        $email->setMailerDTO($dto);
        return $email;
    }


    // @todo: Create MailerRequestDTO interface to use with all mailer request dtos.
    // protected function addEmailSignIfExists(/*MailerRequestDTO*/ object $mailerRequestDTO): object
    // {
    //     if (!$mailerRequestDTO->emailSign) {
    //         return $mailerRequestDTO;
    //     }
    //     $emailSignHtml = $mailerRequestDTO->emailSign;
    //     $signSeparator = config('email.email_sign_separator');
    //     $signEndFlag = config('email.email_sign_end_separator_flag');
    //     $signStartFlag = config('email.email_sign_start_separator_flag');

    //     $body = $mailerRequestDTO->body;
    //     $completeSign = $signStartFlag . $signSeparator . $emailSignHtml . $signEndFlag;
    //     $mailerRequestDTO->body = $body . $completeSign;
    //     return $mailerRequestDTO;
    // }

}
