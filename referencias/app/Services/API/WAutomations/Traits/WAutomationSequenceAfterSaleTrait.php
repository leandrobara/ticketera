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
use App\Helpers\PhonesHelper;
use App\Models\WAutomationLog;
use App\Models\WhatsAppSending;
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


trait WAutomationSequenceAfterSaleTrait
{

    public function findAfterSaleWAutomationByClient(Client $client): ?WAutomationSequence
    {
        return $this->wAutomationSequenceRepository->findOneByClientAndTrigger($client, 'after_sale');
    }

    
    public function createNewClientDefaultAfterSale(Client $client): WAutomationSequence
    {
        $attrs = ['client_id' => $client->id];
        $aut = WAutomationSequence::factory()->newClientDefaultAfterSale()->create($attrs);
        return $aut;
    }

    
    public function applyAfterSale(
        LeadSale $triggeringLeadSale,
        WAutomationSequenceStep $wAutSequenceStep
    ): Collection {
        $wAutomationSequence = $wAutSequenceStep->wAutomationSequence;
        if (!$wAutomationSequence->enabled || $this->isWeekendAndCanNotRun($wAutomationSequence)) {
            return new Collection();
        }

        $existentLog = $this->findExistentLeadSaleLog($triggeringLeadSale, $wAutSequenceStep);
        if ($existentLog) {
            return new Collection();
        }

        $exception = $this->getExceptionIfNotEligible(
            leadTagStatusChangeEventLogs: null,
            triggeringProposalWapSendingMsg: null,
            triggeringLeadSale: $triggeringLeadSale,
            wAutomationSequenceStep: $wAutSequenceStep,
        );
        if ($exception) {
            $wAutomationLog = $this->wAutomationLogService->createAfterSaleWAutomationSequenceStepLog(
                $triggeringLeadSale, $wAutSequenceStep, $exception
            );
            if (!($exception instanceof WAutomationNotToReportException)) {
                report($exception);
            }
            if ($exception instanceof UserWAPINotSyncedException) {
                $this->notificationService->storeWAPISyncError(
                    wAutomationLog: $wAutomationLog,
                    leadId: $triggeringLeadSale->lead_id,
                    clientId: $triggeringLeadSale->client_id,
                    userId: $triggeringLeadSale->lead->user_id,
                );
            }
            return new Collection([$wAutomationLog]);
        }

        try {
            DB::beginTransaction();
            
            $wAutomationLog = $this->wAutomationLogService->createAfterSaleWAutomationSequenceStepLog(
                $triggeringLeadSale, $wAutSequenceStep
            );
            $WAPINewWAutSendingDTO = $this->buildWAPISendingDTOByTriggeringLeadSale(
                wAutomationLog: $wAutomationLog,
                triggeringLeadSale: $triggeringLeadSale,
                wAutomationSequenceStep: $wAutSequenceStep,
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


    public function findLeadSalesEnabledToSend(
        WAutomationSequence $wAutSequence,
        WAutomationSequenceStep $wAutSequenceStep
    ): Collection {
        $originalLeadSales = $this->findLeadSalesByStep($wAutSequence, $wAutSequenceStep);
        $enabledLeadSales = $this->getLeadSalesEnabledToSend($originalLeadSales, $wAutSequenceStep);
        return $enabledLeadSales;
    }


    private function findLeadSalesByStep(
        WAutomationSequence $wAutSequence,
        WAutomationSequenceStep $wAutSequenceStep
    ): Collection {
        $dateEnd = $this->getEndDateToSearchEvents($wAutSequenceStep);
        $dateStart = $this->getStartDateToSearchEvents($wAutSequence, $wAutSequenceStep);
        $leadSales = $this->leadSaleService->findByClientAndDates($wAutSequence->client, $dateStart, $dateEnd);
        return $leadSales;
    }

    
    private function getLeadSalesEnabledToSend(
        Collection $leadSales,
        WAutomationSequenceStep $wAutSequenceStep
    ): Collection {
        $enabledLeadSales = new Collection();
        foreach ($leadSales as $leadSale) {
            $isTimeToSend = $this->isTimeToSendSequenceStep($leadSale, $wAutSequenceStep);
            if ($isTimeToSend) {
                $enabledLeadSales->push($leadSale);
            }
        }
        return $enabledLeadSales;
    }


    public function findExistentLeadSaleLog(
        LeadSale $triggeringLeadSale,
        WAutomationSequenceStep $wAutSequenceStep
    ): ?WAutomationLog {
        return $this->wAutomationLogService->findOneByLeadSaleAndWAutomationSequenceStep(
            $triggeringLeadSale, $wAutSequenceStep
        );
    }


    protected function buildWAPISendingDTOByTriggeringLeadSale(
        LeadSale $triggeringLeadSale,
        WAutomationLog $wAutomationLog,
        WAutomationSequenceStep $wAutomationSequenceStep,
    ): WAPINewWAutomationSendingParametersDTO {
        $dto = new WAPINewWAutomationSendingParametersDTO();
        $dto->isMassive = false;
        $dto->isProposal = false;
        $dto->sendDate = $this->getDateNow();
        $dto->chatMessage = $wAutomationSequenceStep->sendWhatsAppTemplate->body;
        $dto->attachment = $wAutomationSequenceStep->sendWhatsAppTemplate->whatsAppAttachment;
        
        $lead = $triggeringLeadSale->lead;
        $client = $triggeringLeadSale->client;
        $dto->client = $client;
        $dto->user = $lead->user;
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
