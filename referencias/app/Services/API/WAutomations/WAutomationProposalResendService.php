<?php

namespace App\Services\API\WAutomations;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\Lead;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Models\WAutomationLog;
use App\Models\WhatsAppSending;
use App\Models\WhatsAppTemplate;
use App\Services\API\UserService;
use App\Services\API\WAPIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\WAutomationProposal;
use App\Services\API\WAPSenderService;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\WhatsAppSendingService;
use App\Models\WAutomationProposalResendRule;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\WhatsAppTemplateService;
use App\Services\API\WhatsAppAttachmentService;
use App\Services\API\WhatsAppSendingMessageService;
use App\DTO\WAutomations\WAutomationProposalResendDTO;
use App\Services\API\Notifications\NotificationService;
use App\DTO\WAPI\WAPINewWAutomationSendingParametersDTO;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Exceptions\Services\WAutomations\UserWAPINotSyncedException;
use App\Exceptions\Services\WAutomations\WAutomationNotToReportException;
use App\Repositories\WAutomations\WAutomationProposalResendRuleRepository;


class WAutomationProposalResendService
{

    use GetClientFromRequest;


    public function __construct(
        private readonly int $windowHoursLimit,
        private readonly WAPIService $WAPIService,
        private readonly WAPSenderService $WAPSenderService,
        private readonly NotificationService $notificationService,
        private readonly WAutomationLogService $wAutomationLogService,
        private readonly WhatsAppSendingService $whatsAppSendingService,
        private readonly WhatsAppMetaAPIService $whatsAppMetaAPIService,
        private readonly WhatsAppTemplateService $whatsAppTemplateService,
        private readonly WhatsAppSendingMessageService $whatsAppSendingMessageService,
        private readonly WAutomationProposalResendRuleRepository $wAutomationProposalResendRuleRepository,
    ) {
    }


    public function findByWAutomationProposal(WAutomationProposal $wAutomationProposal): WAutomationProposalResendRule
    {
        return $this->wAutomationProposalResendRuleRepository->findByWAutomationProposal($wAutomationProposal);
    }


    public function findRuleByClient(Client $client): WAutomationProposalResendRule
    {
        return $this->wAutomationProposalResendRuleRepository->findRuleByClient($client);
    }


    public function save(WAutomationProposalResendDTO $dto): ?WAutomationProposalResendRule
    {
        $rule = null;
        if ($dto->sendWhatsAppTemplate && is_numeric($dto->sendDelayDays) && $dto->sendHour) {
            $rule = $this->wAutomationProposalResendRuleRepository->findRuleByClient($this->getClient());
            if (!$rule) {
                $rule = $this->wAutomationProposalResendRuleRepository->create($dto);
                return $rule;
            }
            if (!$this->parametersChanged($rule, $dto)) {
                return $rule;
            }

            // Me fijo si ya fue aplicada alguna vez.
            $lastAppliedLog = $this->wAutomationLogService->findOneByWAutomationProposalResendRule($rule);
            if (!$lastAppliedLog) {
                $rule = $this->wAutomationProposalResendRuleRepository->update($rule, $dto);
                return $rule;
            }

            try {
                DB::beginTransaction();
                $this->wAutomationProposalResendRuleRepository->delete($rule);
                $rule = $this->wAutomationProposalResendRuleRepository->create($dto);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }
        return $rule;
    }


    public function apply(WhatsAppSending $originalWapSending): Collection
    {
        $resendRule = $this->wAutomationProposalResendRuleRepository->findEnabledRuleByClient(
            $originalWapSending->client
        );
        if (!$resendRule) {
            return new Collection();
        }
        if (!$this->isTimeToSend($originalWapSending->finished_date, $resendRule)) {
            return new Collection();
        }
        if ($this->isWeekendAndCanNotRun($resendRule)) {
            return new Collection();
        }
        $wAutomationProposal = $resendRule->wAutomationProposal;
        if (!$wAutomationProposal || !$wAutomationProposal->enabled) {
            return new Collection();
        }

        $enabledWapSendingMsgs = new Collection();
        foreach ($originalWapSending->whatsAppSendingMessages as $originalWapSendingMsg) {
            $existentLog = $this->findExistentLog($originalWapSendingMsg, $resendRule);
            if ($existentLog) {
                continue;
            }
            $exception = $this->getExceptionIfNotEligible($resendRule, $originalWapSending, $originalWapSendingMsg);
            if ($exception) {
                $wAutomationLog = $this->wAutomationLogService->createWAutomationProposalResendLog(
                    $originalWapSendingMsg, $resendRule, $exception
                );
                if (!($exception instanceof WAutomationNotToReportException)) {
                    report($exception);
                }
                if ($exception instanceof UserWAPINotSyncedException) {
                    $this->notificationService->storeWAPISyncError(
                        wAutomationLog: $wAutomationLog,
                        userId: $originalWapSending->user_id,
                        leadId: $originalWapSendingMsg->lead_id,
                        clientId: $originalWapSendingMsg->client_id,
                    );
                }
                continue;
            }
            $enabledWapSendingMsgs->push($originalWapSendingMsg);
        }

        if ($enabledWapSendingMsgs->isEmpty()) {
            return new Collection();
        }

        try {
            DB::beginTransaction();
            
            $wAutomationLogs = new Collection();
            foreach ($enabledWapSendingMsgs as $originalWapSendingMsg) {
                $wAutomationLog = $this->wAutomationLogService->createWAutomationProposalResendLog(
                    $originalWapSendingMsg, $resendRule
                );
                // Para que no vaya luego a la BD.
                $wAutomationLog->whatsappSendingMessage()->associate($originalWapSendingMsg);
                $wAutomationLogs->push($wAutomationLog);
            }

            $WAPINewWAutSendingDTO = $this->buildWAPISendingDTOByResendRule(
                resendRule: $resendRule,
                wAutomationLogs: $wAutomationLogs,
                originalWapSending: $originalWapSending,
            );

            if ($this->isWhatsAppMetaAPIForced($WAPINewWAutSendingDTO)) {
                $wabaMatchingTpl = $this->findWABAMatchingTemplate($resendRule, $originalWapSending);
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

        return $wAutomationLogs;
    }


    public function buildWAPISendingDTOByResendRule(
        Collection $wAutomationLogs,
        WhatsAppSending $originalWapSending,
        WAutomationProposalResendRule $resendRule,
    ): WAPINewWAutomationSendingParametersDTO {
        $dto = new WAPINewWAutomationSendingParametersDTO();
        $dto->isProposal = true;
        $dto->sendDate = $this->getDateNow();
        $dto->user = $originalWapSending->user;
        $dto->client = $originalWapSending->client;
        $dto->chatMessage = $resendRule->sendWhatsAppTemplate->body;
        
        $dto->attachment = $resendRule->sendWhatsAppTemplate->whatsAppAttachment;
        if ($resendRule->add_original_attachments) {
            $dto->attachment = $originalWapSending->whatsAppAttachment;
        }

        foreach ($wAutomationLogs as $wAutomationLog) {
            $leadContactPhone = $wAutomationLog->whatsAppSendingMessage->leadContactPhone;
            $dto->addIndividualData($wAutomationLog, $leadContactPhone);
        }
        
        $isMassive = $originalWapSending->whatsAppSendingMessages->pluck('lead_id')->unique()->values()->count() > 1;
        $dto->isMassive = $isMassive;
        return $dto;
    }


    public function parametersChanged(
        WAutomationProposalResendRule $resendRule,
        WAutomationProposalResendDTO $dto
    ): bool {
        $cancellingTags = $resendRule->cancellingTags->pluck('id')->toArray();
        $cancellingStatus = $resendRule->cancellingStatusList->pluck('id')->toArray();
        if (
            $cancellingTags === $dto->cancellingTags->pluck('id')->toArray() &&
            $cancellingStatus === $dto->cancellingStatus->pluck('id')->toArray() &&
            $resendRule->send_whatsapp_template_id === $dto->sendWhatsAppTemplate->id &&
            $resendRule->add_original_attachments === $dto->addOriginalAttachments &&
            $resendRule->do_not_send_weekends === $dto->doNotSendWeekends &&
            $resendRule->cancel_if_proposal_was_already_sent === $dto->cancelIfProposalWasAlreadySent &&
            $resendRule->cancelling_enabled === $dto->cancellingEnabled &&
            $resendRule->enabled === $dto->enabled &&
            $resendRule->send_delay_days === $dto->sendDelayDays &&
            $resendRule->send_hour === $dto->sendHour
        ) {
            return false;
        }
        return true;
    }


    public function findSentProposals(WAutomationProposalResendRule $resendRule): Collection
    {
        $client = $resendRule->client;
        $dateEnd = $this->getEndDateToSearchProposals($resendRule);
        $dateStart = $this->getStartDateToSearchProposals($resendRule);
        $wapProposals = $this->whatsAppSendingService->findProposalsBetweenSentDatesByClient(
            $client, $dateStart, $dateEnd
        );
        return $wapProposals;
    }


    public function getExceptionIfNotEligible(
        WAutomationProposalResendRule $resendRule,
        WhatsAppSending $originalWapSending,
        WhatsAppSendingMessage $originalWapSendingMsg
    ): Exception | WAutomationNotToReportException | UserWAPINotSyncedException | null {
        if ($originalWapSending->paused_date) {
            return new Exception('wap_sending_was_paused');
        }
        if (!$originalWapSending->is_proposal) {
            return new Exception('wap_sending_is_not_a_proposal');
        }
        if ($originalWapSending->cancelled_date) {
            return new Exception('wap_sending_was_cancelled');
        }
        if (!$originalWapSending->finished_date) {
            return new Exception('wap_sending_is_not_finished');
        }
        if ($originalWapSending->is_automation) {
            return new Exception('wap_sending_was_sent_by_an_automation');
        }

        if (!$originalWapSendingMsg->lead) {
            return new Exception('wap_sending_message_has_no_lead');
        }
        if (!$originalWapSendingMsg->leadContactPhone) {
            return new Exception('wap_sending_message_has_no_lead_contact_phone');
        }
        if (!$resendRule->sendWhatsAppTemplate) {
            return new Exception('wap_sending_message_has_no_whatsapp_template');
        }
        if (!$originalWapSendingMsg->success) {
            return new Exception('wap_sending_message_was_not_successfully_sent');
        }
        if (!$originalWapSendingMsg->sent_date) {
            return new Exception('wap_sending_message_was_not_successfully_sent');
        }
        if ($originalWapSendingMsg->paused_date) {
            return new Exception('wap_sending_message_was_paused');
        }
        if (!$originalWapSendingMsg->is_proposal) {
            return new Exception('wap_sending_message_is_not_a_proposal');
        }

        $isWhatsAppMetaAPIForced = $originalWapSending->client->clientSettings->force_whatsapp_meta_api;
        $isUsingWapSender = $originalWapSending->client->clientSettings->enable_whatsapp_sender_job_sending;
        if ($isWhatsAppMetaAPIForced) {
            if (!$originalWapSending->client->clientSettings->enable_whatsapp_meta_api) {
                return new Exception('whatsapp_meta_api_is_not_enabled');
            }
            if (!$originalWapSending->user->whatsAppMetaAPIConnection) {
                return new Exception('whatsapp_meta_api_connection_does_not_exist');
            }
            if (!$resendRule->sendWhatsAppTemplate->meta_id) {
                return new Exception('whatsapp_template_is_not_a_meta_template');
            }

            $wabaMatchingTpl = $this->findWABAMatchingTemplate($resendRule, $originalWapSending);
            if (!$wabaMatchingTpl) {
                throw new Exception('whatsapp_template_has_no_match_for_waba_id');
            }
        } elseif ($isUsingWapSender) {
            if (!$originalWapSending->user->wap_sender_session_phone_number) {
                return new Exception('user_has_not_enabled_wap_sender');
            }
        } else {
            if (!$originalWapSending->isWapiType()) {
                return new Exception('wap_sending_is_not_wapi_type');
            }
            if (!$originalWapSendingMsg->isWapiType()) {
                return new Exception('wap_sending_message_is_not_wapi_type');
            }

            // OJO: Quien marca al user como desviculado ante un error de envío es WAPIService
            // Mirar: en WAPIService -> WAPIHelperUserNotSyncedException. No es necesario hacerlo acá.
            if (!$originalWapSending->user->wapi_is_synced) {
                return new UserWAPINotSyncedException('user_is_not_synced_with_wapi');
            }
            if (!$originalWapSending->user->wapi_session_phone_number) {
                return new UserWAPINotSyncedException('user_is_not_synced_with_wapi');
            }
            if (!$originalWapSending->client->clientSettings->enable_wapi) {
                return new UserWAPINotSyncedException('wapi_is_not_enabled');
            }
        }

        if ($originalWapSendingMsg->cancelled_date) {
            return new Exception('wap_sending_message_was_cancelled');
        }
        if ($originalWapSendingMsg->wautomation_log_id) {
            return new Exception('wap_sending_message_was_sent_by_an_automation');
        }
        
        if ($this->proposalWasAlreadySent($originalWapSendingMsg, $resendRule)) {
            return new WAutomationNotToReportException('wap_sending_message_already_sent_proposal');
        }
        if ($resendRule->cancelling_enabled) {
            if ($this->leadHasCancellingTag($originalWapSendingMsg->lead, $resendRule)) {
                $msg = 'wap_sending_message_lead_has_cancelling_tag';
                return new WAutomationNotToReportException($msg);
            }
            if ($this->leadHasCancellingStatus($originalWapSendingMsg->lead, $resendRule)) {
                $msg = 'wap_sending_message_lead_has_cancelling_status';
                return new WAutomationNotToReportException($msg);
            }
        }
        return null;
    }


    public function proposalWasAlreadySent(
        WhatsAppSendingMessage $originalWapSendingMsg,
        WAutomationProposalResendRule $rule
    ): bool {
        if (!$rule->cancel_if_proposal_was_already_sent) {
            return false;
        }
        $lead = $originalWapSendingMsg->lead;
        $sentCount = $this->whatsAppSendingMessageService->countNonAutomationProposalsByLead($lead);
        return $sentCount > 1;
    }


    public function findExistentLog(
        WhatsAppSendingMessage $wapSendingMsg,
        WAutomationProposalResendRule $resendRule
    ): ?WAutomationLog {
        return $this
            ->wAutomationLogService
            ->findOneByWhatsAppSendingMessageAndWAutomationProposalResendRule($wapSendingMsg, $resendRule)
        ;
    }


    public function leadHasCancellingStatus(Lead $lead, WAutomationProposalResendRule $resendRule): bool
    {
        return $resendRule->cancellingStatusList->contains($lead->status);
    }


    public function leadHasCancellingTag(Lead $lead, WAutomationProposalResendRule $resendRule): bool
    {
        return $resendRule->cancellingTags->intersect($lead->tags)->isNotEmpty();
    }


    public function isTimeToSend(DateTime $originalSentDatetime, WAutomationProposalResendRule $resendRule): bool
    {
        $now = $this->getDateNow();
        $sendDatetime = $this->getDateSend($originalSentDatetime, $resendRule);

        $sendTimeIsReached = $now >= $sendDatetime;
        $limitWindowIsExceeded = $this->isExceededLimitDate($sendDatetime);

        if ($sendTimeIsReached && !$limitWindowIsExceeded) {
            return true;
        }
        return false;
    }


    public function isInHourToApply(WAutomationProposalResendRule $resendRule): bool
    {
        $dateNow = $this->getDateNow();
        $hourNow = (int) $dateNow->format('H');
        $hourArr = explode(':', $resendRule->send_hour);
        $hourToApply = (int) $hourArr[0];
        $hoursAbsoluteDiff = absoluteHoursDiff($hourNow, $hourToApply);
        if ($hoursAbsoluteDiff <= $this->windowHoursLimit) {
            return true;
        }
        return false;
    }


    public function isWeekendAndCanNotRun(WAutomationProposalResendRule $resendRule): bool
    {
        if (!$resendRule->do_not_send_weekends) {
            return false;
        }
        // 6 -> saturday, 0 -> sunday
        $clientTz = new DateTimeZone($resendRule->client->timezone);
        $dayOfWeek = (int) ($this->getDateNow())->setTimezone($clientTz)->format('w');
        if ($dayOfWeek == 6 || $dayOfWeek == 0) {
            return true;
        }
        return false;
    }


    private function findWABAMatchingTemplate(
        WAutomationProposalResendRule $resendRule,
        WhatsAppSending $originalWapSending,
    ): ?WhatsAppTemplate {
        $tplWabaId = $resendRule->sendWhatsAppTemplate->waba_id;
        $userWabaId = $originalWapSending->user->whatsAppMetaAPIConnection->waba_id;
        if ($tplWabaId == $userWabaId) {
            return $resendRule->sendWhatsAppTemplate;
        }

        $wabaMatchingTpl = $this->whatsAppTemplateService->findMatchingTemplateForWaba(
            $resendRule->sendWhatsAppTemplate, $userWabaId
        );
        return $wabaMatchingTpl;
    }


    private function todayIsMonday(Client $client): bool
    {
        return (int) ($this->getDateNow())->setTimezone(new DateTimeZone($client->timezone))->format('w') == 1;
    }


    // PRECONDICIÓN: DATETIME_ACTUAL es MAYOR a $wAutomationSendDate
    public function isExceededLimitDate(DateTime $wAutomationSendDate): bool
    {
        $now = $this->getDateNow();
        $sendDatePlusWindowHours = (clone $wAutomationSendDate)->modify("+{$this->windowHoursLimit} hours");
        return $now >= $sendDatePlusWindowHours;
    }


    private function getDateSend(DateTime $originalSentDateTime, WAutomationProposalResendRule $resendRule): DateTime
    {
        $delayDays = $resendRule->send_delay_days;
        $clientTz = new DateTimeZone($resendRule->client->timezone);
        $clientSentDateTime = (clone $originalSentDateTime)->setTimezone($clientTz);
        
        // Para sumar días, dejo la fecha con el TZ del cliente para evitar desfasajes.
        $sendDateTime = (clone $clientSentDateTime)->modify('+ ' . $delayDays . ' days');
        // Seteo una fecha con el TZ UTC0, y luego le asigno la hora.
        $sendDateTime = (new Datetime('now'))->setDate(
            (int) $sendDateTime->format('Y'), (int) $sendDateTime->format('m'), (int) $sendDateTime->format('d')
        );
        $hourArr = explode(':', $resendRule->send_hour);
        if ($hourArr) {
            $sendDateTime->setTime($hourArr[0], $hourArr[1]);
        } else {
            $sendDateTime->setTime(
                (int) $originalSentDateTime->format('H'), (int) $originalSentDateTime->format('i')
            );
        }

        if ($resendRule->do_not_send_weekends) {
            $clientSendDateTime = (clone $sendDateTime)->setTimezone($clientTz);
            $sendDateIsSunday = (int) $clientSendDateTime->format('w') == 0;
            $sendDateIsSaturday = (int) $clientSendDateTime->format('w') == 6;
            if ($sendDateIsSunday) {
                $sendDateTime->modify('+ 1 day');
            }
            if ($sendDateIsSaturday) {
                $sendDateTime->modify('+ 2 day');
            }
        }
        return $sendDateTime;
    }


    private function getStartDateToSearchProposals(WAutomationProposalResendRule $resendRule): DateTime
    {
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($resendRule->client->timezone);
        $dateTime->setTimezone($clientTz);

        $delayDays = $resendRule->send_delay_days;
        if ($resendRule->do_not_send_weekends && $this->todayIsMonday($resendRule->client)) {
            $delayDays += 2;
        }

        // Que hora es para el sistema cuando para el cliente son las 00:00
        $dateTime->modify("- {$delayDays} days")->setTime(0, 0, 0)->setTimezone($systemTz);
        return $dateTime;
    }


    private function getEndDateToSearchProposals(WAutomationProposalResendRule $resendRule): DateTime
    {
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($resendRule->client->timezone);
        $dateTime->setTimezone($clientTz);
        // Que hora es para el sistema cuando para el cliente son las 23:59:59
        $dateTime->modify("- {$resendRule->send_delay_days} days")->setTime(23, 59, 59)->setTimezone($systemTz);
        return $dateTime;
    }


    private function markWAutomationLogAsNotApplied(WAutomationLog $wAutomationLog): WAutomationLog
    {
        return $this->wAutomationLogService->markAsNotApplied($wAutomationLog);
    }


    protected function isWAPSenderJobSendingEnabled(WAPINewWAutomationSendingParametersDTO $dto): bool
    {
        $userWAPSenderEnabled = $dto->user->wap_sender_session_phone_number;
        $clientWAPSenderEnabled = $dto->client->clientSettings->enable_whatsapp_sender_job_sending;
        return $userWAPSenderEnabled && $clientWAPSenderEnabled;
    }


    protected function isWhatsAppMetaAPIForced(WAPINewWAutomationSendingParametersDTO $dto): bool
    {
        return $dto->client->clientSettings->force_whatsapp_meta_api;
    }


    // Unifico esto acá para poder hacer pruebas cambiando la "fecha actual"
    private function getDateNow(): DateTime
    {
        // return environmentIsNotProduction() ? new DateTime('2025-05-26 14:01:00') : new DateTime('now');
        return new DateTime('now');
    }

}
