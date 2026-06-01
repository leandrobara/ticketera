<?php

namespace App\Services\API;

use DateTime;
use Exception;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use App\Models\WAutomationSequence;
use App\Models\AutomationEmailSend;
use App\Models\WhatsAppSendingMessage;
use App\Models\MongoDB\CalendlyScheduledEvent;
use App\Repositories\CalendlyScheduledEventRepository;
use App\Repositories\Criteria\Sort\EventLogs\SortByCreated;
use App\Repositories\Criteria\Filter\EventLogs\CreatedDateEndCriteria;
use App\Repositories\Criteria\Filter\EventLogs\CreatedDateStartCriteria;



class CalendlyScheduledEventService
{

    public function __construct(
        protected readonly CalendlyScheduledEventRepository $calendlyScheduledEventRepository
    ) {
    }

    // public function list(Client $client, array $opts = []): Collection
    // {
    //     $repoOpts = [
    //         'limit' => $opts['limit'] ?? 100,
    //         'order' => $this->getSortCriteriasByName($opts['order'] ?? ''),
    //         'filters' => $this->getFilterCriteriasByName($opts['filters'] ?? []),
    //     ];
    //     return $this->calendlyScheduledEventRepository->list($client, $repoOpts);
    // }


    public function create(array $calendlyScheduledEventData): CalendlyScheduledEvent
    {
        return $this->calendlyScheduledEventRepository->create($calendlyScheduledEventData);
    }


    // Por ahora el identificador UNIQUE es el atributo "uri"
    public function findOneByUri(string $uri): ?CalendlyScheduledEvent
    {
        $scheduledEvent = $this->calendlyScheduledEventRepository->findOneByUri($uri);
        return $scheduledEvent;
    }
    

    // $searchTerm -> puede ser leadId, un email, o un nombre
    public function findByLeadIdOrEmail(string $leadIdOrEmail): Collection
    {
        $leadIdOrEmail = trim($leadIdOrEmail);
        if (!$leadIdOrEmail) {
            return new Collection();
        }
        if (ctype_digit($leadIdOrEmail)) {
            $leadId = (int) $leadIdOrEmail;
            return $this->calendlyScheduledEventRepository->findByLeadId($leadId);
        }
        if (filter_var($leadIdOrEmail, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower($leadIdOrEmail);
            return $this->calendlyScheduledEventRepository->findByLeadEmail($email);
        }
        return new Collection();
    }


    public function findFirstByLeadId(int $leadId): ?CalendlyScheduledEvent
    {
        $scheduledEvent = $this->calendlyScheduledEventRepository->findFirstByLeadId($leadId);
        return $scheduledEvent;
    }
    

    public function findLastByLeadId(int $leadId): ?CalendlyScheduledEvent
    {
        $scheduledEvent = $this->calendlyScheduledEventRepository->findLastByLeadId($leadId);
        return $scheduledEvent;
    }

}
