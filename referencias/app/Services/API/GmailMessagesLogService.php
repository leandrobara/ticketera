<?php

namespace App\Services\API;

use DateTime;
use Exception;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use App\Services\API\EmailService;
use Illuminate\Support\Collection;
use App\Models\MongoDB\GmailMessageLog;
use App\Repositories\GmailMessageLogRepository;
use App\DTO\GoogleAPI\GoogleAPIGmailMessageDTO;
use App\Exceptions\Services\GoogleAPI\NoClientMatchException;
use App\Repositories\Criteria\Filter\GmailMessageLogs\GmailMessageLogUserCriteria;
use App\Repositories\Criteria\Filter\GmailMessageLogs\GmailMessageLogTypeCriteria;
use App\Repositories\Criteria\Filter\GmailMessageLogs\GmailMessageLogLeadCriteria;
use App\Repositories\Criteria\Filter\GmailMessageLogs\GmailMessageLogClientCriteria;
use App\Repositories\Criteria\Filter\GmailMessageLogs\GmailMessageLogGmailIdCriteria;
use App\Repositories\Criteria\Filter\GmailMessageLogs\GmailMessageLogSentDateEndCriteria;
use App\Repositories\Criteria\Filter\GmailMessageLogs\GmailMessageLogSentDateStartCriteria;


class GmailMessagesLogService
{

    public function __construct(
        protected readonly GmailMessageLogRepository $gmailMessageLogRepository,
        protected readonly LeadService $leadService,
        protected readonly EmailService $emailService
    ) {
    }


    // @returns Collection<GoogleAPIGmailMessageDTO>
    public function list(Client $client, array $opts = []): Collection
    {
        $opts['filters']['client_id'] = $client->id;
        $repoOpts = [
            'fields' => $opts['fields'] ?? [],
            'offset' => (int) ($opts['offset'] ?? 0),
            'limit' => (int) ($opts['limit'] ?? 100),
            'excludeFields' => $opts['excludeFields'] ?? [],
            'sort' => $opts['sort'] ?? ['sentDate' => 'desc'],
            'filters' => $this->getFilterCriteriasByName($opts['filters'] ?? []),
        ];
        $gmailMessages = $this->gmailMessageLogRepository->list($client, $repoOpts);
        $messagesDtos = $gmailMessages->map(function ($gmailMessageLog) use ($opts) {
            $dto = GoogleAPIGmailMessageDTO::buildFromMongoDoc($gmailMessageLog);
            if (in_array('lead', $opts['with'] ?? [])) {
                $dto->lead = $this->leadService->find(
                    $dto->clientyMetadata['lead']['id'], ['failIfNotExists' => false]
                );
                // Si el lead fue borrado, remuevo el dto (y después filtro)
                if (!$dto->lead) {
                    return null;
                }
            }
            return $dto;
        });
        $messagesDtos = $messagesDtos->filter(fn ($doc) => $doc ? true : false);
        return $messagesDtos;
    }


    public function count(Client $client, array $opts = []): int
    {
        $filters = ($opts['filters'] ?? []) + ['client_id' => $client->id];
        $repoOpts = ['filters' => $this->getFilterCriteriasByName($filters)];
        return $this->gmailMessageLogRepository->count($repoOpts);
    }


    public function findByLead(Lead $lead, array $opts = []): Collection
    {
        $opts['filters'] = ['lead_id' => $lead->id, 'client_id' => $lead->client_id];
        $messagesDtos = $this->list($lead->client, $opts);
        return $messagesDtos;
    }


    public function findByUser(User $user, array $opts = []): Collection
    {
        $opts['filters'] = ['user_id' => $user->id, 'client_id' => $user->client_id];
        $messagesDtos = $this->list($user->client, $opts);
        return $messagesDtos;
    }


    public function findOneByClientAndGmailId(Client $client, string $gmailId): ?GoogleAPIGmailMessageDTO
    {
        $opts = ['limit' => 1, 'filters' => ['gmail_id' => $gmailId, 'client_id' => $client->id]];
        $messages = $this->list($client, $opts);
        $messageDto = $messages->first();
        if ($messageDto) {
            $clientId = $messageDto->clientyMetadata['client']['id'] ?? null;
            if ($clientId != $client->id) {
                return null;
            }
        }
        return $messageDto;
    }


    public function store(Client $client, GoogleAPIGmailMessageDTO $dto): GoogleAPIGmailMessageDTO
    {
        $dto = $this->fillMessageClientyMetadata($dto);
        $dtoClientId = (int) $dto->clientyMetadata['client']['id'];
        if ($dtoClientId != $client->id) {
            throw new NoClientMatchException('Client ID does not match');
        }

        $gmailMessageLog = $this->gmailMessageLogRepository->store($client, $dto);
        $dto = GoogleAPIGmailMessageDTO::buildFromMongoDoc($gmailMessageLog);
        return $dto;
    }


    protected function fillMessageClientyMetadata(GoogleAPIGmailMessageDTO $dto): GoogleAPIGmailMessageDTO
    {
        $email = $this->emailService->findOneOrFailByExternalId($dto->clientyMetadata['email']['external_id']);
        $dto->clientyMetadata['email']['id'] = $email->id;
        $dto->clientyMetadata['user'] = [
            'id' => $email->user->id,
            'name' => $email->user->name,
            'email' => $email->user->email,
            'username' => $email->user->username,
            'last_name' => $email->user->last_name,
            'email_from_address' => $email->user->email_from_address,
        ];
        return $dto;
    }


    protected function getFilterCriteriasByName(array $filters): array
    {
        $criterias = [
            'type' => GmailMessageLogTypeCriteria::class,
            'user_id' => GmailMessageLogUserCriteria::class,
            'lead_id' => GmailMessageLogLeadCriteria::class,
            'gmail_id' => GmailMessageLogGmailIdCriteria::class,
            'client_id' => GmailMessageLogClientCriteria::class,
            'send_date_end' => GmailMessageLogSentDateEndCriteria::class,
            'send_date_start' => GmailMessageLogSentDateStartCriteria::class,
        ];
        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias)) && $value !== null) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] = $value;
            }
        }
        return $nfilters;
    }

}