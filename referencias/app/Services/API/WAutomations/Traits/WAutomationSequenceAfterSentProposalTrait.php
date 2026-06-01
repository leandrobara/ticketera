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
use App\Models\WAutomationLog;
use App\Models\WhatsAppSending;
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


trait WAutomationSequenceAfterSentProposalTrait
{

    public function findAfterSentProposalWAutomationByClient(Client $client): ?WAutomationSequence
    {
        return $this->wAutomationSequenceRepository->findOneByClientAndTrigger($client, 'after_sent_proposal');
    }

    
    public function createNewClientDefaultAfterSentProposal(Client $client): WAutomationSequence
    {
        $attrs = ['client_id' => $client->id];
        $aut = WAutomationSequence::factory()->newClientDefaultAfterSentProposal()->create($attrs);
        return $aut;
    }


    public function applyAfterSentProposal(
        WhatsAppSending $triggeringProposalWapSending,
        WAutomationSequenceStep $wAutomationSequenceStep
    ): Collection {
        $wAutomationSequence = $wAutomationSequenceStep->wAutomationSequence;
        if (!$wAutomationSequence->enabled || $this->isWeekendAndCanNotRun($wAutomationSequence)) {
            return new Collection();
        }

        $nonAppliedWAutomationLogs = new Collection();
        $enabledWapSendingMsgs = new Collection();
        foreach ($triggeringProposalWapSending->whatsAppSendingMessages as $triggeringProposalWapSendingMsg) {
            $existentLog = $this->findExistentProposalLog($triggeringProposalWapSendingMsg, $wAutomationSequenceStep);
            if ($existentLog) {
                continue;
            }
            $triggeringProposalWapSendingMsg->whatsAppSending()->associate($triggeringProposalWapSending);
            $exception = $this->getExceptionIfNotEligible(
                triggeringLeadSale: null,
                leadTagStatusChangeEventLogs: null,
                wAutomationSequenceStep: $wAutomationSequenceStep,
                triggeringProposalWapSendingMsg: $triggeringProposalWapSendingMsg,
            );
            if ($exception) {
                $wAutomationLog = $this->wAutomationLogService->createAfterSentProposalWAutomationSequenceStepLog(
                    $triggeringProposalWapSendingMsg, $wAutomationSequenceStep, $exception
                );
                if (!($exception instanceof WAutomationNotToReportException)) {
                    report($exception);
                }
                if ($exception instanceof UserWAPINotSyncedException) {
                    $this->notificationService->storeWAPISyncError(
                        wAutomationLog: $wAutomationLog,
                        leadId: $triggeringProposalWapSendingMsg->lead_id,
                        userId: $triggeringProposalWapSendingMsg->user_id,
                        clientId: $triggeringProposalWapSendingMsg->client_id,
                    );
                }

                $nonAppliedWAutomationLogs->push($wAutomationLog);
                continue;
            }
            $enabledWapSendingMsgs->push($triggeringProposalWapSendingMsg);
        }

        if ($enabledWapSendingMsgs->isEmpty()) {
            return $nonAppliedWAutomationLogs;
        }

        try {
            DB::beginTransaction();
            
            $appliedWAutomationLogs = new Collection();
            foreach ($enabledWapSendingMsgs as $triggeringProposalWapSendingMsg) {
                $wAutomationLog = $this->wAutomationLogService->createAfterSentProposalWAutomationSequenceStepLog(
                    $triggeringProposalWapSendingMsg, $wAutomationSequenceStep
                );
                // Para que no vaya luego a la BD.
                $wAutomationLog->whatsappSendingMessage()->associate($triggeringProposalWapSendingMsg);
                $appliedWAutomationLogs->push($wAutomationLog);
            }

            $WAPINewWAutSendingDTO = $this->buildWAPISendingDTOByTriggeringSentProposal(
                wAutomationLogs: $appliedWAutomationLogs,
                wAutomationSequenceStep: $wAutomationSequenceStep,
                triggeringProposalWapSending: $triggeringProposalWapSending,
            );

            if ($this->isWhatsAppMetaAPIForced($WAPINewWAutSendingDTO)) {
                $wabaMatchingTpl = $this->findWABAMatchingTemplate(
                    $wAutomationSequenceStep, $WAPINewWAutSendingDTO->user
                );
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

        return $appliedWAutomationLogs->merge($nonAppliedWAutomationLogs);
    }


    public function findSentWapSendingProposalsEnabledToSend(
        WAutomationSequence $wAutSequence,
        WAutomationSequenceStep $wAutSequenceStep
    ): Collection {
        $triggeringProposalWapSendings = $this->findSentWapSendingProposalsByStep($wAutSequence, $wAutSequenceStep);
        $enabledWapSendingProposals = $this->getWapSendingProposalsEnabledToSend(
            $triggeringProposalWapSendings, $wAutSequenceStep
        );
        return $enabledWapSendingProposals;
    }


    private function findSentWapSendingProposalsByStep(
        WAutomationSequence $wAutSequence,
        WAutomationSequenceStep $wAutSequenceStep
    ): Collection {
        $dateEnd = $this->getEndDateToSearchEvents($wAutSequenceStep);
        $dateStart = $this->getStartDateToSearchEvents($wAutSequence, $wAutSequenceStep);
        $wapSendingProposals = $this->whatsAppSendingService->findProposalsBetweenSentDatesByClient(
            $wAutSequence->client, $dateStart, $dateEnd
        );
        return $wapSendingProposals;
    }


    private function getWapSendingProposalsEnabledToSend(
        Collection $wapSendingProposals,
        WAutomationSequenceStep $wAutSequenceStep
    ): Collection {
        $enabledWapSendingProposals = new Collection();
        foreach ($wapSendingProposals as $wapSendingProposal) {
            $isTimeToSend = $this->isTimeToSendSequenceStep($wapSendingProposal, $wAutSequenceStep);
            if ($isTimeToSend) {
                $enabledWapSendingProposals->push($wapSendingProposal);
            }
        }
        return $enabledWapSendingProposals;
    }


    public function findExistentProposalLog(
        WhatsAppSendingMessage $triggeringProposalWapSendingMsg,
        WAutomationSequenceStep $wAutSequenceStep
    ): ?WAutomationLog {
        return $this
            ->wAutomationLogService
            ->findOneByWhatsAppSendingMessageAndWAutomationSequenceStep(
                $triggeringProposalWapSendingMsg, $wAutSequenceStep
            )
        ;
    }



    protected function buildWAPISendingDTOByTriggeringSentProposal(
        Collection $wAutomationLogs,
        WhatsAppSending $triggeringProposalWapSending,
        WAutomationSequenceStep $wAutomationSequenceStep,
    ): WAPINewWAutomationSendingParametersDTO {
        $dto = new WAPINewWAutomationSendingParametersDTO();
        $dto->isMassive = false;
        $dto->isProposal = false;
        $dto->sendDate = $this->getDateNow();
        $dto->chatMessage = $wAutomationSequenceStep->sendWhatsAppTemplate->body;
        $dto->attachment = $wAutomationSequenceStep->sendWhatsAppTemplate->whatsAppAttachment;

        $dto->user = $triggeringProposalWapSending->user;
        $dto->client = $triggeringProposalWapSending->client;

        foreach ($wAutomationLogs as $wAutomationLog) {
            $leadContactPhone = $wAutomationLog->whatsAppSendingMessage->leadContactPhone;
            $dto->addIndividualData($wAutomationLog, $leadContactPhone);
        }
        return $dto;
    }

}
