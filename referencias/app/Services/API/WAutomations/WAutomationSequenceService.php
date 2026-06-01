<?php

namespace App\Services\API\WAutomations;

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
use App\Models\WhatsAppTemplate;
use App\Models\MongoDB\EventLog;
use App\Models\LeadContactPhone;
use App\Services\API\UserService;
use App\Services\API\LeadService;
use App\Services\API\WAPIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Helpers\MermaidChartHelper;
use App\Models\WAutomationSequence;
use App\Services\API\StatusService;
use App\Services\API\LeadSaleService;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\EventsLogService;
use App\Services\API\WAPSenderService;
use App\Models\WAutomationSequenceStep;
use App\Services\Traits\GetUserFromRequest;
use App\Services\API\WhatsAppSendingService;
use App\Services\API\WhatsAppTemplateService;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\WAutomations\WAutomationSequenceDTO;
use App\Services\API\Notifications\NotificationService;
use App\DTO\WAPI\WAPINewWAutomationSendingParametersDTO;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\Services\API\WAutomations\WAutomationSequenceStepService;
use App\Exceptions\Services\WAutomations\UserWAPINotSyncedException;
use App\Exceptions\Services\WAutomations\WAutomationNotToReportException;
use App\Services\API\WAutomations\Traits\WAutomationSequenceAfterSaleTrait;
use App\Services\API\WAutomations\Traits\WAutomationSequenceAfterSentProposalTrait;
use App\Services\API\WAutomations\Traits\WAutomationSequenceAfterTagStatusChangeTrait;
use App\Repositories\WAutomations\WAutomationSequenceRepository as WAutomationSequenceRepo;
use App\Repositories\Cache\WAutomationSequenceRepositoryCache as WAutomationSequenceRepoCache;


class WAutomationSequenceService
{

    use GetUserFromRequest;
    use GetClientFromRequest;
    use WAutomationSequenceAfterSaleTrait;
    use WAutomationSequenceAfterSentProposalTrait;
    use WAutomationSequenceAfterTagStatusChangeTrait;

    const LIMIT_WINDOW_HOURS = 3;
    const PRECAUTION_BAND_END_HOUR = 8; // 08:00
    const PRECAUTION_BAND_START_HOUR = 21; // 21:00
    const LIMIT_WINDOW_MINUTES_SAME_DAY = 45;


    public function __construct(
        private readonly WAPIService $WAPIService,
        protected readonly UserService $userService,
        protected readonly LeadService $leadService,
        protected readonly StatusService $statusService,
        protected readonly LeadSaleService $leadSaleService,
        protected readonly EventsLogService $eventsLogService,
        protected readonly WAPSenderService $WAPSenderService,
        protected readonly MermaidChartHelper $mermaidChartHelper,
        protected readonly NotificationService $notificationService,
        protected readonly WAutomationLogService $wAutomationLogService,
        protected readonly WhatsAppSendingService $whatsAppSendingService,
        protected readonly WhatsAppMetaAPIService $whatsAppMetaAPIService,
        protected readonly WhatsAppTemplateService $whatsAppTemplateService,
        protected readonly EmailEventsDispatcherService $emailEventsDispatcherService,
        protected readonly WAutomationSequenceStepService $wAutomationSequenceStepService,
        protected readonly WAutomationSequenceRepo | WAutomationSequenceRepoCache $wAutomationSequenceRepository,
    ) {
    }


    public function findByClient(Client $client): Collection
    {
        return $this->wAutomationSequenceRepository->findByClient($client);
    }
    

    public function save(WAutomationSequenceDTO $dto): WAutomationSequence
    {
        $wAutomation = $this->wAutomationSequenceRepository->findOneByClientAndTrigger(
            $this->getClient(), $dto->triggerType
        );
        if (!$wAutomation) {
            $wAutomation = $this->create($dto);
            return $wAutomation;
        }
        if (!$this->parametersChanged($wAutomation, $dto)) {
            return $wAutomation;
        }
        $wAutomation = $this->update($wAutomation, $dto);
        return $wAutomation;
    }


    public function create(WAutomationSequenceDTO $dto): WAutomationSequence
    {
        return $this->wAutomationSequenceRepository->create($dto);
    }


    public function update(WAutomationSequence $wAutomation, WAutomationSequenceDTO $dto): WAutomationSequence
    {
        if (!$this->parametersChanged($wAutomation, $dto)) {
            return $wAutomation;
        }

        // If rule was never applied, I can update the row.
        $existentLog = $this->wAutomationLogService->findOneByWAutomationSequence($wAutomation, ['limit' => 1]);
        if (!$existentLog) {
            $wAutomation = $this->wAutomationSequenceRepository->update($wAutomation, $dto);
            return $wAutomation;
        }

        try {
            DB::beginTransaction();

            // If rule was applied at least once, I soft-delete it and create a new one.
            $this->wAutomationSequenceRepository->delete($wAutomation);
            $newWAutomation = $this->wAutomationSequenceRepository->create($dto);
            $newSteps = $this->wAutomationSequenceStepService->cloneWAutomationSequenceSteps(
                $wAutomation, $newWAutomation
            );
            $this->wAutomationSequenceStepService->deleteAllByWAutomationSequence($wAutomation);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $newWAutomation;
    }


    public function delete(WAutomationSequence $wAutomation): WAutomationSequence
    {
        try {
            DB::beginTransaction();
            $this->wAutomationSequenceStepService->deleteAllByWAutomationSequence($wAutomation);
            $deletedWAutomation = $this->wAutomationSequenceRepository->delete($wAutomation);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->emailEventsDispatcherService->dispatchSendDeletedAutomationEmailAlertJob(
            $deletedWAutomation, $this->getUser()
        );
        return $deletedWAutomation;
    }


    public function enable(WAutomationSequence $wAutomation): WAutomationSequence
    {
        return $this->wAutomationSequenceRepository->enable($wAutomation);
    }


    public function disable(WAutomationSequence $wAutomation): WAutomationSequence
    {
        $disabledWAutomation = $this->wAutomationSequenceRepository->disable($wAutomation);
        $this->emailEventsDispatcherService->dispatchSendDisabledAutomationEmailAlertJob(
            $disabledWAutomation, $this->getUser()
        );
        return $disabledWAutomation;
    }


    public function parametersChanged(WAutomationSequence $wAutomation, WAutomationSequenceDTO $dto)
    {
        $cancellingTags = $wAutomation->cancellingTags->pluck('id')->toArray();
        $triggeringTags =  $wAutomation->triggeringTags->pluck('id')->toArray();
        $cancellingStatus = $wAutomation->cancellingStatus->pluck('id')->toArray();
        $triggeringStatus = $wAutomation->triggeringStatus->pluck('id')->toArray();

        if (
            $wAutomation->name == $dto->name &&
            $wAutomation->enabled == $dto->enabled &&
            $wAutomation->trigger_type == $dto->triggerType &&
            $wAutomation->do_not_send_weekends == $dto->doNotSendWeekends &&
            $cancellingTags == $dto->cancellingTags->pluck('id')->toArray() &&
            $triggeringTags == $dto->triggeringTags->pluck('id')->toArray() &&
            $cancellingStatus == $dto->cancellingStatus->pluck('id')->toArray() &&
            $triggeringStatus == $dto->triggeringStatus->pluck('id')->toArray() &&
            $wAutomation->cancel_if_sequence_was_sent == $dto->cancelIfSequenceWasSent
        ) {
            return false;
        }

        return true;
    }


    private function getStartDateToSearchEvents(
        WAutomationSequence $wAutomation,
        WAutomationSequenceStep $wAutSequenceStep
    ): DateTime {
        $client = $wAutSequenceStep->client;
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        $dateTime->setTimezone($clientTz);

        // Si el mensaje se envia el MISMO día a los XX minutos
        if ($wAutSequenceStep->isToSendSameDay()) {
            $limitWindowMinutes = self::LIMIT_WINDOW_MINUTES_SAME_DAY;
            $delayMinutes = $wAutSequenceStep->send_delay_minutes + $limitWindowMinutes;
            $dateTime->modify("- {$delayMinutes} minutes")->setTimezone($systemTz);
            return $dateTime;
        }

        // if automation has the "do not send in weekends" flag
        // add 2 days only if it runs in monday to retrieve leads
        // before the weekend
        $delayDays = $wAutSequenceStep->send_delay_days;
        if ($wAutomation->do_not_send_weekends && $this->todayIsMonday($client)) {
            $delayDays += 2;
        }
        // Que hora es para el sistema cuando para el cliente son las 00:00
        $dateTime->modify("- {$delayDays} days")->setTime(0, 0, 0)->setTimezone($systemTz);
        return $dateTime;
    }


    private function getEndDateToSearchEvents(WAutomationSequenceStep $wAutSequenceStep): DateTime
    {
        $client = $wAutSequenceStep->client;
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        $dateTime->setTimezone($clientTz);

        // Si el mensaje se envia el MISMO día a los XX minutos
        if ($wAutSequenceStep->isToSendSameDay()) {
            $delayMinutes = $wAutSequenceStep->send_delay_minutes;
            $dateTime->modify("- {$delayMinutes} minutes")->setTimezone($systemTz);
            return $dateTime;
        }

        // Que hora es para el sistema cuando para el cliente son las 23:59:59
        $dateTime->modify("- {$wAutSequenceStep->send_delay_days} days")->setTime(23, 59, 59)->setTimezone($systemTz);
        return $dateTime;
    }


    private function isTimeToSendSequenceStep(
        EventLog | WhatsAppSending | LeadSale $object,
        WAutomationSequenceStep $wAutSequenceStep
    ): bool {
        $dateNow = $this->getDateNow();

        if ($object instanceof EventLog) {
            $stepSendDate = $this->getStepSendDate($object->createdAt, $wAutSequenceStep);
        } elseif ($object instanceof WhatsAppSending) {
            $stepSendDate = $this->getStepSendDate($object->finished_date, $wAutSequenceStep);
        } elseif ($object instanceof LeadSale) {
            $stepSendDate = $this->getStepSendDate($object->sale_date, $wAutSequenceStep);
        }

        // Si el mensaje se envia el MISMO día a los XX minutos
        if ($wAutSequenceStep->isToSendSameDay()) {
            $limitWindowMinutes = self::LIMIT_WINDOW_MINUTES_SAME_DAY;
            $maxStepSendDate = (clone($stepSendDate))->modify("+{$limitWindowMinutes} minutes");
        } else {
            $limitWindowHours = self::LIMIT_WINDOW_HOURS;
            $maxStepSendDate = (clone($stepSendDate))->modify("+{$limitWindowHours} hours");
        }

        $sendDateReached = $dateNow >= $stepSendDate;
        $sendWindowExceeded = $dateNow >= $maxStepSendDate;
        return ($sendDateReached && !$sendWindowExceeded);
    }


    public function isInHourToApply(WAutomationSequenceStep $wAutSequenceStep): bool
    {
        // No aplica si no tiene hora (se evalúa por evento en isTimeToSendSequenceStep -> getStepSendDate)
        if ($wAutSequenceStep->isToSendSameDay()) {
            return true;
        }
        // Horario original del evento (también se evalúa luego en isTimeToSendSequenceStep -> getStepSendDate)
        if ($this->isOriginalHourStep($wAutSequenceStep)) {
            $clientTz = new DateTimeZone($wAutSequenceStep->client->timezone);
            $hourNow = (int) ($this->getDateNow())->setTimezone($clientTz)->format('H');
            // No se hacen envíos durante la banda
            if ($this->isInPrecautionBand($hourNow)) {
                return false;
            }
            return true;
        }
        if (!$wAutSequenceStep->send_hour) {
            return true; // Fallback de seguridad
        }

        $dateNow = $this->getDateNow();
        $hourNow = (int) $dateNow->format('H');
        $hourArr = explode(':', $wAutSequenceStep->send_hour);
        $hourToApply = (int) $hourArr[0];
        $hoursAbsoluteDiff = absoluteHoursDiff($hourNow, $hourToApply);
        if ($hoursAbsoluteDiff <= self::LIMIT_WINDOW_HOURS) {
            return true;
        }
        return false;
    }


    private function getStepSendDate(DateTime $eventCreationDate, WAutomationSequenceStep $wAutSequenceStep): DateTime
    {
        // Si el mensaje se envia el MISMO día a los XX minutos
        if ($wAutSequenceStep->isToSendSameDay()) {
            $sendDate = (clone $eventCreationDate)->modify('+ ' . $wAutSequenceStep->send_delay_minutes . ' minutes');
            return $sendDate;
        }

        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($wAutSequenceStep->client->timezone);
        $doNotSendWeekends = $wAutSequenceStep->wAutomationSequence->do_not_send_weekends;

        // === Horario original (send_hour es NULL) ===
        if ($this->isOriginalHourStep($wAutSequenceStep)) {
            // Tomamos la fecha del evento trigger con su fecha y su horario original
            $sendDate = (clone $eventCreationDate);
            // Convertir a timezone del cliente para trabajar con su hora local
            $sendDate->setTimezone($clientTz);

            // Sumar los días de delay
            $sendDate->modify('+ ' . $wAutSequenceStep->send_delay_days . ' days');

            // Manejar fines de semana si corresponde
            if ($doNotSendWeekends && $this->dateIsWeekend($sendDate)) {
                $sendDate->modify('next monday');
            }

            // Ajustar si cae en banda de precaución (21:00-08:00)
            $sendDate = $this->adjustDateForPrecautionBand($sendDate);

            // Verificar de nuevo si el ajuste de banda causó que caiga en fin de semana
            if ($doNotSendWeekends && $this->dateIsWeekend($sendDate)) {
                $sendDate->modify('next monday');
                $sendDate->setTime(self::PRECAUTION_BAND_END_HOUR, 0, 0);
            }

            // Convertir a UTC0
            $sendDate->setTimezone($systemTz);
            return $sendDate;
        }

        // === Hora fija configurada (lógica ya existente antes de implementar horario original) ===
        $sendDate = (clone $eventCreationDate);
        // Uso el TZ del cliente para saber que día era para el cliente, y luego sumarle XX días.
        $sendDate->setTimezone($clientTz)->setTime(0, 0, 0);
        $sendDate->modify('+ ' . $wAutSequenceStep->send_delay_days . ' days');

        // Si tuvo que haber sido enviado un fin de semana, y hoy es lunes
        // la fecha de envío entonces es el proximo lunes a la fecha original de envío.
        $stepSendDateIsWeekend = $this->dateIsWeekend($sendDate);
        $todayIsMonday = $this->todayIsMonday($wAutSequenceStep->wAutomationSequence->client);
        if ($doNotSendWeekends && $stepSendDateIsWeekend && $todayIsMonday) {
            $sendDate = $sendDate->modify('next monday');
        }

        // La hora guardada siempre está en UTC0. (Ej: 13:00 de argentina se guarda como 16:00)
        // Uso el TZ del cliente para saber la hora en su timezone.
        $hourArr = explode(':', $wAutSequenceStep->send_hour);
        $clientStepSendHour = ($this->getDateNow())->setTime((int) $hourArr[0], (int) $hourArr[1], 0);
        $clientStepSendHour = $clientStepSendHour->setTimezone($clientTz);
        
        $clientHourArr = explode(':', $clientStepSendHour->format('H:i'));
        $sendDate->setTime((int) $clientHourArr[0], (int) $clientHourArr[1], 0);

        // Una vez que tengo armada la fecha/hora en TZ del cliente, la paso a UTC0.
        $sendDate->setTimezone($systemTz);

        return $sendDate;
    }


    public function isWeekendAndCanNotRun(WAutomationSequence $wAutomation): bool
    {
        $client = $wAutomation->client;
        // 6 -> saturday, 0 -> sunday
        $dayOfWeek = (int) ($this->getDateNow())->setTimezone(new DateTimeZone($client->timezone))->format('w');
        if ($wAutomation->do_not_send_weekends && ($dayOfWeek == 6 || $dayOfWeek == 0)) {
            return true;
        }
        return false;
    }


    protected function getExceptionIfNotEligible(
        LeadSale | null $triggeringLeadSale,
        Collection | null $leadTagStatusChangeEventLogs,
        WAutomationSequenceStep $wAutomationSequenceStep,
        WhatsAppSendingMessage | null $triggeringProposalWapSendingMsg,
    ): Exception | UserWAPINotSyncedException | WAutomationNotToReportException | null {
        $wAutSequence = $wAutomationSequenceStep->wAutomationSequence;
        
        if (!$wAutomationSequenceStep->sendWhatsAppTemplate) {
            return new Exception('wap_sending_message_has_no_whatsapp_template');
        }
            
        if ($wAutSequence->isAfterSentProposalType) {
            if (!$triggeringProposalWapSendingMsg) {
                return new Exception('triggering_wap_sending_message_proposal_is_missing');
            }

            $triggeringProposalWapSending = $triggeringProposalWapSendingMsg->whatsAppSending;
            $user = $triggeringProposalWapSendingMsg->user;
            $lead = $triggeringProposalWapSendingMsg->lead;
            $client = $triggeringProposalWapSendingMsg->client;

            if (!$lead) {
                return new Exception('wap_sending_message_has_no_lead');
            }
            if ($triggeringProposalWapSending->paused_date) {
                return new Exception('wap_sending_was_paused');
            }
            if ($triggeringProposalWapSending->cancelled_date) {
                return new Exception('wap_sending_was_cancelled');
            }
            if (!$triggeringProposalWapSending->finished_date) {
                return new Exception('wap_sending_is_not_finished');
            }
            if ($triggeringProposalWapSending->is_automation) {
                return new Exception('wap_sending_was_sent_by_an_automation');
            }
            if (!$triggeringProposalWapSending->is_proposal) {
                return new Exception('wap_sending_is_not_a_proposal');
            }

            if (!$triggeringProposalWapSendingMsg->leadContactPhone) {
                return new Exception('wap_sending_message_has_no_lead_contact_phone');
            }
            if (!$triggeringProposalWapSendingMsg->success) {
                return new Exception('wap_sending_message_was_not_successfully_sent');
            }
            if (!$triggeringProposalWapSendingMsg->sent_date) {
                return new Exception('wap_sending_message_was_not_successfully_sent');
            }
            if ($triggeringProposalWapSendingMsg->paused_date) {
                return new Exception('wap_sending_message_was_paused');
            }
            if ($triggeringProposalWapSendingMsg->cancelled_date) {
                return new Exception('wap_sending_message_was_cancelled');
            }
            if ($triggeringProposalWapSendingMsg->wautomation_log_id) {
                return new Exception('wap_sending_message_was_sent_by_an_automation');
            }
            if (!$triggeringProposalWapSendingMsg->is_proposal) {
                return new Exception('wap_sending_message_is_not_a_proposal');
            }
        }

        if ($wAutSequence->isAfterSaleType) {
            if (!$triggeringLeadSale) {
                return new Exception('triggering_wap_sending_message_proposal_is_missing');
            }
            
            $lead = $triggeringLeadSale->lead;
            $client = $triggeringLeadSale->client;
            if (!$lead) {
                return new Exception('wap_sending_message_has_no_lead');
            }
            if ($lead->leadContactPhones->isEmpty()) {
                return new WAutomationNotToReportException('triggering_lead_sale_lead_has_no_contact_phones');
            }
            $validLeadContactPhones = $lead->leadContactPhones
                ->filter(function (LeadContactPhone $leadContactPhone) use ($client) {
                    return resolve(PhonesHelper::class)->leadContactPhoneNumberHasValidLength(
                        $leadContactPhone, $client
                    );
                })
            ;
            if ($validLeadContactPhones->isEmpty()) {
                return new WAutomationNotToReportException('triggering_lead_sale_lead_has_no_valid_contact_phones');
            }
            $user = $triggeringLeadSale->lead->user;
        }
        
        if ($wAutSequence->isAfterTagStatusChangeType) {
            $leadId = $leadTagStatusChangeEventLogs->first()->log['lead']['id'];
            $lead = $this->leadService->find($leadId, ['failIfNotExists' => false]);
            if (!$lead) {
                return new Exception('lead_tag_status_change_events_lead_no_longer_exists');
            }

            if ($lead->leadContactPhones->isEmpty()) {
                return new WAutomationNotToReportException(
                    'triggering_lead_tag_status_change_lead_has_no_contact_phones'
                );
            }

            $user = $lead->user;
            $client = $user->client;
            $validLeadContactPhones = $lead->leadContactPhones
                ->filter(function (LeadContactPhone $leadContactPhone) use ($client) {
                    return resolve(PhonesHelper::class)->leadContactPhoneNumberHasValidLength(
                        $leadContactPhone, $client
                    );
                })
            ;
            if ($validLeadContactPhones->isEmpty()) {
                return new WAutomationNotToReportException(
                    'triggering_lead_tag_status_change_lead_has_no_valid_contact_phones'
                );
            }
        }
        
        if (!$user) {
            return new Exception('user_was_deleted');
        }
        if (!$user->enabled) {
            return new Exception('user_is_not_enabled');
        }

        $isWhatsAppMetaAPIForced = $client->clientSettings->force_whatsapp_meta_api;
        $isUsingWapSender = $client->clientSettings->enable_whatsapp_sender_job_sending;
        if ($isWhatsAppMetaAPIForced) {
            if (!$client->clientSettings->enable_whatsapp_meta_api) {
                return new Exception('whatsapp_meta_api_is_not_enabled');
            }
            if (!$user->whatsAppMetaAPIConnection) {
                return new Exception('whatsapp_meta_api_connection_does_not_exist');
            }
            if (!$user->whatsAppMetaAPIConnection->waba_id) {
                return new Exception('whatsapp_meta_api_connection_has_no_waba_id_related');
            }
            if (!$wAutomationSequenceStep->sendWhatsAppTemplate->meta_id) {
                return new Exception('whatsapp_template_is_not_a_meta_template');
            }

            $wabaMatchingTpl = $this->findWABAMatchingTemplate($wAutomationSequenceStep, $user);
            if (!$wabaMatchingTpl) {
                return new Exception('whatsapp_template_has_no_match_for_waba_id');
            }
        } elseif ($isUsingWapSender) {
            if (!$user->wap_sender_session_phone_number) {
                return new Exception('user_has_not_enabled_wap_sender');
            }
        } else {
            // OJO: Quien marca al user como desviculado ante un error de envío es WAPIService
            // Mirar: en WAPIService -> WAPIHelperUserNotSyncedException. No es necesario hacerlo acá.
            if (!$user->wapi_is_synced) {
                return new UserWAPINotSyncedException('user_is_not_synced_with_wapi');
            }
            if (!$user->wapi_session_phone_number) {
                return new UserWAPINotSyncedException('user_is_not_synced_with_wapi');
            }
            if (!$client->clientSettings->enable_wapi) {
                return new UserWAPINotSyncedException('wapi_is_not_enabled');
            }
        }

        $cancellingTags = $wAutSequence->cancelling_tags_ids ? $wAutSequence->cancellingTags : new Collection();
        if ($cancellingTags->isNotEmpty()) {
            $leadHasCancellingTag = $cancellingTags->intersect($lead->tags)->isNotEmpty();
            if ($leadHasCancellingTag) {
                $msg = 'wap_sending_message_lead_has_cancelling_tag';
                return new WAutomationNotToReportException($msg);
            }
        }

        $cancellingStatusList = $wAutSequence->cancelling_status_ids
            ? $wAutSequence->cancellingStatus
            : new Collection()
        ;
        if ($cancellingStatusList->isNotEmpty()) {
            $leadHasCancellingStatus = $cancellingStatusList->contains($lead->status);
            if ($leadHasCancellingStatus) {
                $msg = 'wap_sending_message_lead_has_cancelling_status';
                return new WAutomationNotToReportException($msg);
            }
        }

        if ($wAutSequence->isTagTriggered) {
            $leadHasTriggeringTag = $wAutSequence->triggeringTags->intersect($lead->tags)->isNotEmpty();
            if (!$leadHasTriggeringTag) {
                $msg = 'wap_sending_message_lead_has_not_triggering_tag_anymore';
                return new WAutomationNotToReportException($msg);
            }
        }
        if ($wAutSequence->isStatusTriggered) {
            $triggeringStatusList = $wAutSequence->triggeringStatus;
            $leadHasTriggeringStatus = $triggeringStatusList->contains($lead->status);
            if (!$leadHasTriggeringStatus) {
                $msg = 'wap_sending_message_lead_has_not_triggering_status_anymore';
                return new WAutomationNotToReportException($msg);
            }
        }

        if ($wAutSequence->cancel_if_sequence_was_sent) {
            $existentStepLog = $this->wAutomationLogService->findOneByLeadIdAndWAutomationSequenceStep(
                $lead->id, $wAutomationSequenceStep
            );
            if ($existentStepLog) {
                return new WAutomationNotToReportException('wautomation_sequence_was_already_applied_in_the_past');
            }
        }
        return null;
    }


    private function findWABAMatchingTemplate(
        WAutomationSequenceStep $wAutomationSequenceStep,
        User $user,
    ): ?WhatsAppTemplate {
        $tplWabaId = $wAutomationSequenceStep->sendWhatsAppTemplate->waba_id;
        $userWabaId = $user->whatsAppMetaAPIConnection->waba_id;
        if ($tplWabaId == $userWabaId) {
            return $wAutomationSequenceStep->sendWhatsAppTemplate;
        }

        $wabaMatchingTpl = $this->whatsAppTemplateService->findMatchingTemplateForWaba(
            $wAutomationSequenceStep->sendWhatsAppTemplate, $userWabaId
        );
        return $wabaMatchingTpl;
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


    private function todayIsMonday(Client $client): bool
    {
        return (int) ($this->getDateNow())->setTimezone(new DateTimeZone($client->timezone))->format('w') == 1;
    }


    private function dateIsWeekend(DateTime $date): bool
    {
        return ((int) $date->format('w')) == 0 || ((int) $date->format('w')) == 6;
    }


    private function isOriginalHourStep(WAutomationSequenceStep $step): bool
    {
        return $step->send_delay_days > 0 && $step->send_hour === null;
    }


    private function isInPrecautionBand(int $hour): bool
    {
        return $hour >= self::PRECAUTION_BAND_START_HOUR || $hour < self::PRECAUTION_BAND_END_HOUR;
    }


    private function adjustDateForPrecautionBand(DateTime $sendDate): DateTime
    {
        $hour = (int) $sendDate->format('H');

        if (!$this->isInPrecautionBand($hour)) {
            return $sendDate;
        }

        // Si es entre 21:00-23:59, pasar al día siguiente a las 08:00
        if ($hour >= self::PRECAUTION_BAND_START_HOUR) {
            $sendDate->modify('+1 day');
        }
        // En ambos casos (21-23:59 o 00-07:59), setear a las 08:00
        $sendDate->setTime(self::PRECAUTION_BAND_END_HOUR, 0, 0);

        return $sendDate;
    }


    private function getDateNow(): DateTime
    {
        // return environmentIsNotProduction() ? new DateTime('2025-01-28 13:00:13') : new DateTime('now');
        return new DateTime('now');
    }


    public function getFlowChartMarkdownString(WAutomationSequence $wAutomationSequence): string
    {
        $markdown = $this->mermaidChartHelper->buildWAutomationSequenceMarkdown($wAutomationSequence);
        return $markdown;
    }

}
