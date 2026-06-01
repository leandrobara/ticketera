<?php

namespace App\Services\API\WAutomations\Traits;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\LeadSale;
use Illuminate\Log\Logger;
use App\Helpers\PhonesHelper;
use App\Models\WAutomationLog;
use App\Models\WhatsAppSending;
use App\Models\MongoDB\EventLog;
use App\Models\LeadContactPhone;
use App\Services\API\UserService;
use App\Services\API\LeadService;
use App\Services\API\WAPIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\WAutomationSequence;
use App\Services\API\StatusService;
use App\Services\API\LeadSaleService;
use App\Models\WhatsAppSendingMessage;
use App\Models\WAutomationSequenceStep;
use App\Services\API\WhatsAppSendingService;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\WAutomations\WAutomationSequenceDTO;
use App\Services\API\Notifications\NotificationService;
use App\DTO\WAPI\WAPINewWAutomationSendingParametersDTO;
use App\Repositories\WAutomations\WAutomationSequenceRepository;
use App\Services\API\WAutomations\WAutomationSequenceStepService;
use App\Exceptions\Services\WAutomations\UserWAPINotSyncedException;
use App\Exceptions\Services\WAutomations\WAutomationNotToReportException;


trait WAutomationSequenceAfterTagStatusChangeTrait
{

    public function findAfterTagStatusChangeWAutomationByClient(Client $client): ?Collection
    {
        return $this->wAutomationSequenceRepository->findByClientAndTrigger($client, 'after_tag_status_change');
    }

    
    public function createNewClientDefaultAfterTagStatusChange(Client $client): WAutomationSequence
    {
        $attrs = ['client_id' => $client->id];
        $aut = WAutomationSequence::factory()->newClientDefaultAfterSale()->create($attrs);
        return $aut;
    }

    
    // Si el step encontró más de una coincidencia (ej: 2 etiquetas agregadas a un lead), acá se manda el leadId
    // y los eventos encontrados de ese lead (ej: esos dos eventos de esas 2 etiquetas).
    public function applyAfterTagStatusChange(
        int $leadId,
        Collection $leadTagStatusChangeEventLogs,
        WAutomationSequenceStep $wAutSequenceStep,
        ?Logger $infoLog = null
    ): Collection {
        $wAutSequence = $wAutSequenceStep->wAutomationSequence;
        if (!$wAutSequence->enabled) {
            if ($infoLog) {
                $infoLog->info("applyAfterTagStatusChange::wAutSequence [NOT ENABLED]");
            }
            return new Collection();
        }
        if ($this->isWeekendAndCanNotRun($wAutSequence)) {
            if ($infoLog) {
                $infoLog->info("applyAfterTagStatusChange::wAutSequence [isWeekendAndCanNotRun]");
            }
            return new Collection();
        }

        $wAutStepWasAlreadyApplied = $this->wAutStepWasAlreadyApplied(
            $leadId, $leadTagStatusChangeEventLogs, $wAutSequenceStep
        );
        if ($wAutStepWasAlreadyApplied) {
            if ($infoLog) {
                $infoLog->info("applyAfterTagStatusChange::wAutSequenceStep [wAutStepWasAlreadyApplied]");
            }
            return new Collection();
        }

        $exception = $this->getExceptionIfNotEligible(
            triggeringLeadSale: null,
            triggeringProposalWapSendingMsg: null,
            wAutomationSequenceStep: $wAutSequenceStep,
            leadTagStatusChangeEventLogs: $leadTagStatusChangeEventLogs,
        );
        if ($exception) {
            $wAutomationLog = $this->wAutomationLogService->createAfterTagStatusChangeWAutomationSequenceStepLog(
                $leadTagStatusChangeEventLogs, $wAutSequenceStep, $exception
            );
            if (!($exception instanceof WAutomationNotToReportException)) {
                report($exception);
            }
            if ($exception instanceof UserWAPINotSyncedException) {
                $leadId = $leadTagStatusChangeEventLogs->first()->log['lead']['id'];
                $lead = $this->leadService->find($leadId);
                $this->notificationService->storeWAPISyncError(
                    leadId: $lead->id,
                    userId: $lead->user_id,
                    clientId: $lead->client_id,
                    wAutomationLog: $wAutomationLog,
                );
            }
            return new Collection([$wAutomationLog]);
        }

        try {
            DB::beginTransaction();
            
            $wAutomationLog = $this->wAutomationLogService->createAfterTagStatusChangeWAutomationSequenceStepLog(
                $leadTagStatusChangeEventLogs, $wAutSequenceStep
            );
            $WAPINewWAutSendingDTO = $this->buildWAPISendingDTOByTriggeringEvents(
                wAutomationLog: $wAutomationLog,
                wAutomationSequenceStep: $wAutSequenceStep,
                leadTagStatusChangeEventLogs: $leadTagStatusChangeEventLogs,
            );
            
            if ($this->isWhatsAppMetaAPIForced($WAPINewWAutSendingDTO)) {
                $wabaMatchingTpl = $this->findWABAMatchingTemplate($wAutSequenceStep, $WAPINewWAutSendingDTO->user);
                $this->whatsAppMetaAPIService->createNewWAutomationSending($wabaMatchingTpl, $WAPINewWAutSendingDTO);
            } elseif ($this->isWAPSenderJobSendingEnabled($WAPINewWAutSendingDTO)) {
                $this->WAPSenderService->createNewWAutomationSending($WAPINewWAutSendingDTO);
            } else {
                $this->WAPIService->createNewWAutomationSending($WAPINewWAutSendingDTO);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return new Collection([$wAutomationLog]);
    }


    // @return Collection<leadId => Collection<EventLog>>
    public function findEventLogsEnabledToSendGroupedByLeadId(
        WAutomationSequence $wAutSequence,
        WAutomationSequenceStep $wAutSequenceStep
    ): Collection {
        $dateEnd = $this->getEndDateToSearchEvents($wAutSequenceStep);
        $dateStart = $this->getStartDateToSearchEvents($wAutSequence, $wAutSequenceStep);
        
        $triggeringEventLogs = $this->eventsLogService->findStatusOrTagChangeEventLogsByAutomation(
            $wAutSequence, $dateStart, $dateEnd, ['limit' => 150]
        );
        // Filtro de protección extra.
        $triggeringEventLogs = $triggeringEventLogs->filter(
            function (EventLog $eventLog) use ($wAutSequence, $dateStart, $dateEnd) {
                if ($wAutSequence->isTagTriggered) {
                    if ($eventLog->event != 'lead_tag_added') {
                        return false;
                    }
                    $tagId = $eventLog->log['tag']['id'];
                    $tagIdExists = $wAutSequence->triggeringTags->pluck('id')->contains($tagId);
                    if (!$tagIdExists) {
                        return false;
                    }
                }
                if ($wAutSequence->isStatusTriggered) {
                    if ($eventLog->event != 'lead_status_updated') {
                        return false;
                    }
                    $statusId = $eventLog->log['status']['id'];
                    $statusIdExists = $wAutSequence->triggeringStatus->pluck('id')->contains($statusId);
                    if (!$statusIdExists) {
                        return false;
                    }
                }
                
                $eventLogCreatedDate = $eventLog->createdAt->toDateTime();
                $isCorrectClient = $eventLog->log['client_id'] == $wAutSequence->client_id;
                $isCorrectDate = $eventLogCreatedDate >= $dateStart && $eventLogCreatedDate <= $dateEnd;
                return $isCorrectClient && $isCorrectDate;
            }
        );

        $enabledEventLogs = $this->getEventLogsEnabledToSend($triggeringEventLogs, $wAutSequenceStep);
        $eventLogsGroupedByLead = $enabledEventLogs->groupBy('log.lead.id');
        return $eventLogsGroupedByLead;
    }


    // @return Collection<EventLog>
    private function getEventLogsEnabledToSend(
        Collection $triggeringEventLogs,
        WAutomationSequenceStep $wAutSequenceStep
    ): Collection {
        if ($triggeringEventLogs->isEmpty()) {
            return $triggeringEventLogs;
        }

        $enabledEventLogs = new Collection();
        $leadIds = $triggeringEventLogs->pluck('log.lead.id');
        $leads = $this->leadService->findByIds($leadIds, ['getRawResult' => true, 'fields' => ['id']]);
        $leads = $leads->mapWithKeys(fn ($lead) => [$lead->id => $lead->id]);

        $enabledEventLogs = $triggeringEventLogs->filter(
            function (EventLog $eventLog) use ($wAutSequenceStep, $leads) {
                $isTimeToSend = $this->isTimeToSendSequenceStep($eventLog, $wAutSequenceStep);
                if (!$isTimeToSend) {
                    return false;
                }
                $leadExists = $leads->has($eventLog->log['lead']['id']);
                return $leadExists;
            }
        );
        return $enabledEventLogs;
    }


    public function wAutStepWasAlreadyApplied(
        int $leadId,
        Collection $leadEventLogs,
        WAutomationSequenceStep $wAutSequenceStep
    ): bool {
        $existentWAutStepLogs = $this->wAutomationLogService->findByLeadIdAndWAutomationSequenceStep(
            $leadId, $wAutSequenceStep
        );
        $eventLogIdsToApply = $leadEventLogs->pluck('id');
        $existentEventLogIds = $existentWAutStepLogs->pluck('event_log_ids')->flatten()->unique()->values();
        $eventLogIdsAlreadyApplied = $eventLogIdsToApply->intersect($existentEventLogIds);
        $sequenceWasApplied = $eventLogIdsAlreadyApplied->isNotEmpty();
        return $sequenceWasApplied;
    }


    // @param $leadTagStatusChangeEventLogs -> Collection<EventLog>
    protected function buildWAPISendingDTOByTriggeringEvents(
        WAutomationLog $wAutomationLog,
        Collection $leadTagStatusChangeEventLogs,
        WAutomationSequenceStep $wAutomationSequenceStep,
    ): WAPINewWAutomationSendingParametersDTO {
        $dto = new WAPINewWAutomationSendingParametersDTO();
        $dto->isMassive = false;
        $dto->isProposal = false;
        $dto->sendDate = $this->getDateNow();
        $dto->chatMessage = $wAutomationSequenceStep->sendWhatsAppTemplate->body;
        $dto->attachment = $wAutomationSequenceStep->sendWhatsAppTemplate->whatsAppAttachment;
        
        // @todo Mejorar esta lógica, ya va a la BD en WAutomationSequenceService::getExceptionIfNotEligible
        $leadId = $leadTagStatusChangeEventLogs->first()->log['lead']['id'];
        $lead = $this->leadService->find($leadId);
        $dto->user = $lead->user;
        $client = $lead->client;
        $dto->client = $lead->client;
        $validLeadContactPhones = $lead->leadContactPhones
            ->filter(function (LeadContactPhone $leadContactPhone) use ($client) {
                return resolve(PhonesHelper::class)->leadContactPhoneNumberHasValidLength($leadContactPhone, $client);
            })
        ;
        foreach ($validLeadContactPhones as $leadContactPhone) {
            $dto->addIndividualData($wAutomationLog, $leadContactPhone);
        }
        return $dto;
    }

}
