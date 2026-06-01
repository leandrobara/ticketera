<?php

namespace App\Services\API\Views;

use DateTime;
use DateTimeZone;
use App\Models\Email;
use App\Models\Client;
use App\DTO\MailerEmailDTO;
use App\DTO\EmailQuotaInfoDTO;
use App\DTO\MassiveEmailModalDTO;
use App\DTO\SentEmailModalInfoDTO;
use App\DTO\MailerMassiveEmailDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Helpers\MongoSearchHelper;
use App\Repositories\EmailRepository;
use App\Helpers\ClientyMailerAPIHelper;
use App\Services\API\AttachmentService;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\LeadContactEmailService;
use Illuminate\Pagination\LengthAwarePaginator;
use App\DTO\MailerEmailListRequestParametersDTO;
use App\Repositories\EmailNotificationLogRepository;
use App\DTO\MailerMassiveEmailListRequestParametersDTO;
use App\Repositories\Criteria\Filter\Emails\IsOpenedCriteria;
use App\Repositories\Criteria\Filter\Emails\SentOnlyCriteria;
use App\Repositories\Criteria\Filter\Emails\OpenedOnlyCriteria;
use App\DTO\ClientyConfigurations\ClientEmailSendingMetricsDTO;
use App\Repositories\Criteria\Filter\Emails\SendDateEndCriteria;
use App\Repositories\Criteria\Filter\Emails\SendDateStartCriteria;
use App\DTO\ClientyConfigurations\EmailQuotaInfoDTO as ClientyConfigEmailQuotaInfoDTO;


class EmailService
{

    use GetClientFromRequest;


    public function __construct(
        private readonly EmailRepository $emailRepository,
        private readonly AttachmentService $attachmentService,
        private readonly MongoSearchHelper $mongoSearchHelper,
        private readonly ClientyMailerAPIHelper $clientyMailerAPIHelper,
        private readonly LeadContactEmailService $leadContactEmailService,
        private readonly EmailNotificationLogRepository $emailNotificationLogRepository,
    ) {
    }


    public function findSentEmails(array $requestParams): LengthAwarePaginator
    {
        $sentEmailsPaginated = $this->findPaginatedList($requestParams);
        $sentEmails = $sentEmailsPaginated->getCollection();
        $sentEmails = $this->fillEmailsWithMailerInfo($sentEmails);
        return $sentEmailsPaginated;
    }


    public function findByIdsWithMailerInfo(array $emailIds): Collection
    {
        $emails = $this->emailRepository->findByIdsAndClient($emailIds, $this->getClient());
        $emails = $this->fillEmailsWithMailerInfo($emails);
        return $emails;
    }


    public function showSentEmailModalInfo(Email $email): Email
    {
        $client = $this->getClient();

        $modalInfoData = $this->fillEmailWithModalInfo($email);
        $mailerDTOAttachments = $modalInfoData->getMailerDTO()->get('attachments');

        $names = collect($mailerDTOAttachments)->pluck('name');
        $hashes = collect($mailerDTOAttachments)->pluck('hash');

        $attachments = $this->attachmentService
            ->findByClientAndHashes($client, $hashes)
            ->whereIn('name', $names)
        ;
        $attachments = $attachments->map(function ($attachment) {
            return ['id' => $attachment->id, 'name' => $attachment->name, 'size' => $attachment->size];
        })->filter()->toArray();
        
        $modalInfoData['attachments'] = $attachments;
        return $modalInfoData;
    }


    public function fillEmailWithModalInfo(Email $email): Email
    {
        $mailerFields = ['id', 'subject', 'from', 'from_name', 'body', 'attachments'];
        $email = $this->fillEmailWithMailerInfo($email, $mailerFields);
        return $email;
    }


    public function fillMassiveEmailWithModalInfo(Email $email): Email
    {
        $mailerFields = ['id', 'subject', 'from', 'from_name', 'body', 'attachments'];
        $email = $this->fillEmailWithMailerInfo($email, $mailerFields);
        return $email;
    }


    public function fillEmailsWithMailerInfo(Collection $emails, $mailerFields = []): Collection
    {
        if ($emails->isEmpty()) {
            return $emails;
        }
        
        $mailerFields = $mailerFields
            ? $mailerFields
            : ['id', 'subject', 'opened_at', 'bounced_at', 'cancelled_at', 'complained_at', 'unsubscribed_at']
        ;
        $mailerRequestParams = ['fields' => $mailerFields];
        $mailerResponse = $this->getMailerEmailsData($emails, $mailerRequestParams);
        $mailerEmails = collect($mailerResponse['data'] ?? []);

        foreach ($emails as $email) {
            $mailerEmailArr = $mailerEmails->firstWhere('id', $email->external_id);
            if ($mailerEmailArr) {
                // Fix: un Email en Mailer puede estar rebotado pero como rebote BLANDO.
                // Si eso sucede, el Email de Clienty NO tendrá fecha de rebote.
                // Solo se informa desde Mailer, al tercer rebote blando de un mismo emailAddr.
                // Clienty-Mailer::BouncedEmailNotificationJob -> linea 41
                if (!$email->bounced_date) {
                    $mailerEmailArr['bouncedAt'] = null;
                }
                $dto = MailerEmailDTO::buildFromEmail($mailerEmailArr);
                $email->setMailerDTO($dto);
            }
        }
        return $emails;
    }


    public function fillEmailWithMailerInfo(Email $email, $mailerFields = []): Email
    {
        $emails = $this->fillEmailsWithMailerInfo(collect([$email]), $mailerFields);
        return $emails->first();
    }


    public function getDailyUsedQuota(Client $client): int
    {
        $utcTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        $dateStart = (new DateTime('now'))->setTimezone($clientTz)->setTime(0, 0, 0)->setTimezone($utcTz);
        $dateEnd = (new DateTime('now'))->setTimezone($clientTz)->setTime(23, 59, 59)->setTimezone($utcTz);
        $total = $this->emailRepository->getSentOrScheduledCountByRange($client, $dateStart, $dateEnd);
        return $total;
    }


    public function getMonthlyUsedQuota(Client $client): int
    {
        $utcTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        $startLocal = (new DateTime('now', $clientTz))->modify('first day of this month')->setTime(0, 0, 0);
        $endLocalExcl = (clone $startLocal)->modify('first day of next month')->setTime(0, 0, 0);
        $dateStart = (clone $startLocal)->setTimezone($utcTz);
        $dateEnd   = (clone $endLocalExcl)->setTimezone($utcTz);
        $total = $this->emailRepository->getSentOrScheduledCountByRange($client, $dateStart, $dateEnd);
        return $total;
    }


    public function getEmailQuotaInfoDTO(Client $client): EmailQuotaInfoDTO
    {
        $dailyQuota = $client->clientSettings->daily_email_sending_quota;
        $monthlyQuota = $client->clientSettings->monthly_email_sending_quota;

        $dailyUsedQuota = $this->getDailyUsedQuota($client);
        $monthlyUsedQuota = $this->getMonthlyUsedQuota($client);

        $availableDailyQuota = $dailyQuota - $dailyUsedQuota;
        $availableMonthlyQuota = $monthlyQuota - $monthlyUsedQuota;

        if ($availableDailyQuota < 0) {
            $availableDailyQuota = 0;
        }
        if ($availableMonthlyQuota < 0) {
            $availableMonthlyQuota = 0;
        }

        $dto = new EmailQuotaInfoDTO();
        $dto->dailyQuota = $dailyQuota;
        $dto->monthlyQuota = $monthlyQuota;
        $dto->dailyUsedQuota = $dailyUsedQuota;
        $dto->monthlyUsedQuota = $monthlyUsedQuota;
        $dto->availableDailyQuota = $availableDailyQuota;
        $dto->availableMonthlyQuota = $availableMonthlyQuota;

        return $dto;
    }


    // Los quota de envío se toman día y mes calendario (envíos hechos hoy, envíos hechos este mes calendario).
    public function getMassiveEmailModalInfo(Collection $leadIds): MassiveEmailModalDTO
    {
        $client = $this->getClient();
        $emailQuotaInfoDTO = $this->getEmailQuotaInfoDTO($client); // EmailQuotaInfoDTO
        $leadContactEmails = $this->leadContactEmailService->findByClientAndLeadIds($client, $leadIds);
        
        $emailSendingBlocked = $client->clientSettings->email_sending_blocked ?? false;

        $dto = new MassiveEmailModalDTO();
        $dto->leadContactEmails = $leadContactEmails;
        $dto->emailQuotaInfoDTO = $emailQuotaInfoDTO;
        $dto->emailSendingBlocked = $emailSendingBlocked;
        return $dto;
    }


    public function getClientyConfigEmailQuotaInfoDTO(Client $client): ClientyConfigEmailQuotaInfoDTO
    {
        $quotaInfoDTO = $this->getEmailQuotaInfoDTO($client);

        $dto = new ClientyConfigEmailQuotaInfoDTO();

        $dto->dailyQuota = $quotaInfoDTO->dailyQuota;
        $dto->dailyUsedQuota = $quotaInfoDTO->dailyUsedQuota;
        $dto->availableDailyQuota = $quotaInfoDTO->availableDailyQuota;
        
        $dto->monthlyQuota = $quotaInfoDTO->monthlyQuota;
        $dto->monthlyUsedQuota = $quotaInfoDTO->monthlyUsedQuota;
        $dto->availableMonthlyQuota = $quotaInfoDTO->availableMonthlyQuota;
        
        return $dto;
    }


    public function getClientyConfigClientModalMetricsInfo(
        Client $client,
        array $opts = []
    ): ClientEmailSendingMetricsDTO {
        $dto = $this->emailRepository->getClientyConfigClientModalMetricsInfo($client, $opts);
        return $dto;
    }


    public function findMassiveSentEmails(array $requestParams): LengthAwarePaginator
    {
        // $requestParams['filters']['sent_only'] = true;
        $massiveSentEmailsPaginated = $this->findPaginatedMassiveList($requestParams);

        $massiveSentEmails = $massiveSentEmailsPaginated->getCollection();
        if ($massiveSentEmails->isNotEmpty()) {
            $mailerResponse = $this->getMailerMassiveEmailsData($massiveSentEmails);
            $mailerMassiveEmails = collect($mailerResponse['data'] ?? []);
        }

        foreach ($massiveSentEmails as $massiveSentEmail) {
            $massiveSentEmail->leads_count = $this->emailRepository->findTotalLeadsForMassiveSend(
                $massiveSentEmail->external_massive_id
            );
            $mailerMassiveEmailArr = $mailerMassiveEmails->firstWhere(
                'massive_sending_id', $massiveSentEmail->external_massive_id
            );
            if ($mailerMassiveEmailArr) {
                // Fix: un Email en Mailer puede estar rebotado pero como rebote BLANDO.
                // Si eso sucede, el Email de Clienty NO tendrá fecha de rebote.
                // Solo se informa desde Mailer, al tercer rebote blando de un mismo emailAddr.
                // Clienty-Mailer::BouncedEmailNotificationJob -> linea 41
                // En este caso, cuento los rebotes de Clienty.Emails para determinar si son duros y contabilizarlos.
                $mailerMassiveEmailArr['bounced_count'] = $massiveSentEmail->bounced_count;
                $dto = MailerMassiveEmailDTO::buildFromMassiveEmail($mailerMassiveEmailArr);
                $massiveSentEmail->setMailerMassiveDTO($dto);
            }
        }
        return $massiveSentEmailsPaginated;
    }


    public function findPaginatedList(array $requestParams)
    {
        $client = $this->getClient();
        $filters = $requestParams['filters'] ?? [];

        $search = $filters['search'] ?? null;
        if ($search) {
            $leadDocs = $this->mongoSearchHelper->search(
                $search, ['filters' => ['client_id' => $client->id], 'fields' => ['id']]
            );
            $leadIds = $leadDocs->map(function ($leadDoc) {
                return (int) $leadDoc['id'];
            });
            $filters['lead_id'] = $leadIds->push(-1)->values()->toArray();
            unset($filters['search']);
        }

        $options = [
            'page' => $requestParams['page'] ?? 1,
            'limit' => $requestParams['limit'] ?? 20,
            'sort' => $requestParams['sort'] ?? null,
            'with' => $requestParams['with'] ?? null,
            'filters' => $this->getFilterCriteriasByName($filters),
        ];
        return $this->emailRepository->findPaginatedEmailsByClient($client, $options);
    }


    public function findPaginatedMassiveList(array $requestParams)
    {
        $client = $this->getClient();
        $options = [
            'page' => $requestParams['page'] ?? 1,
            'limit' => $requestParams['limit'] ?? 50,
            'sort' => $requestParams['sort'] ?? 'desc',
            'filters' => $this->getFilterCriteriasByName($requestParams['filters'] ?? []),
        ];

        return $this->emailRepository->findPaginatedMassiveEmailsByClient($client, $options);
    }


    public function getMailerEmailsData(Collection $emails, array $requestParams): array
    {
        $dto = MailerEmailListRequestParametersDTO::buildFromEmails($emails);
        $dto->limit = $emails->count();
        $dto->fields = $requestParams['fields'] ?? null;
        $response = $this->clientyMailerAPIHelper->getSentEmails($dto->toArray());
        return $response;
    }


    public function getMailerMassiveEmailsData(Collection $massiveEmails, array $requestParams = []): array
    {
        $dto = MailerMassiveEmailListRequestParametersDTO::buildFromEmails($massiveEmails);
        $dto->limit = $massiveEmails->count();
        $dto->fields = $requestParams['fields'] ?? null;
        return $this->clientyMailerAPIHelper->getMassiveSentEmails($dto->toArray());
    }


    public function list(array $options, ?Client $client = null): LengthAwarePaginator
    {
        $opts = [
            'page' => $options['page'] ?? 1,
            'with' => $options['with'] ?? [],
            'limit' => $options['limit'] ?? 20,
            'sort' => $options['sort'] ?? 'desc',
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];
        $client = $client ?? $this->getClient();
        $response = $this->emailRepository->listPaginated($client, $opts);
        return $response;
    }


    public function listEmailMassiveToExport(array $options): Collection
    {
        if (!($options['filters']['external_massive_id'] ?? null)) {
            throw new \Exception('external_massive_id parameters is required');
        }
        $options['limit'] = 999999999;
        $response = $this->list($options);
        return $response->getCollection();
    }


    public function listEmailMassiveOpenToExport(array $options, ?Client $client = null): LengthAwarePaginator
    {
        $opts = [
            'page' => $options['page'] ?? 1,
            'with' => $options['with'] ?? [],
            'limit' => $options['limit'] ?? 20,
            'sort' => $options['sort'] ?? 'desc',
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];
        $client = $client ?? $this->getClient();
        $response = $this->emailNotificationLogRepository->listPaginated($client, $opts);
        return $response;
    }


    private function getFilterCriteriasByName(array $filters): array
    {
        $criterias = [
            'is_opened' => IsOpenedCriteria::class,
            'sent_only' => SentOnlyCriteria::class,
            'opened_only' => OpenedOnlyCriteria::class,
            'send_date_end' => SendDateEndCriteria::class,
            'send_date_start' => SendDateStartCriteria::class
        ];
        $nfilters = [];
        foreach ($filters as $key => $value) {
            if ($key != 'is_opened' && !$value) {
                continue;
            }

            if (in_array($key, array_keys($criterias))) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] =  $value;
            }
        }
        return $nfilters;
    }

}

