<?php

namespace App\Services\API\WAutomations;

use DateTime;
use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\Client;
use App\Models\WAutomationLog;
use App\Models\WhatsAppSending;
use App\Services\API\TagService;
use App\Services\API\NoteService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\WAutomationAfterSend;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\Actions\LeadService;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\WAutomations\WAutomationAfterSendDTO;
use App\Services\API\WAutomations\WAutomationLogService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Repositories\WAutomations\WAutomationAfterSendRepository;
use App\Exceptions\Services\WAutomations\UserWAPINotSyncedException;
use App\Exceptions\Services\WAutomations\WAutomationNotToReportException;



class WAutomationAfterSendService
{

    use GetClientFromRequest;


    public function __construct(
        protected readonly WAutomationAfterSendRepository $wAutomationAfterSendRepository,
        protected readonly WAutomationLogService $wAutomationLogService,
        protected readonly LeadService $actionsLeadService,
        protected readonly NoteService $noteService,
        protected readonly TagService $tagService,
    ) {
    }


    public function findOneByClient(Client $client): ?WAutomationAfterSend
    {
        return $this->wAutomationAfterSendRepository->findOneByClient($client);
    }


    public function findOneEnabledByClientId(int $clientId): ?WAutomationAfterSend
    {
        return $this->wAutomationAfterSendRepository->findOneEnabledByClientId($clientId);
    }


    public function save(WAutomationAfterSendDTO $dto): WAutomationAfterSend
    {
        $client = $this->getClient();
        if ($dto->client->id != $client->id) {
            throw new Exception('wautomation_after_send_invalid_client');
        }
        $wAutomation = $this->findOneByClient($client);
        if (!$wAutomation) {
            $wAutomation = $this->create($dto);
            return $wAutomation;
        }
        $wAutomation = $this->update($wAutomation, $dto); // update() se fija solo si los parámetros cambiaron
        return $wAutomation;
    }


    public function create(WAutomationAfterSendDTO $dto): WAutomationAfterSend
    {
        return $this->wAutomationAfterSendRepository->create($dto);
    }


    public function update(
        WAutomationAfterSend $wAutomation,
        WAutomationAfterSendDTO $dto
    ): WAutomationAfterSend {
        if (!$this->parametersChanged($wAutomation, $dto)) {
            return $wAutomation;
        }

        // If was never applied, I can update the row.
        $appliedLog = $this->wAutomationLogService->findOneByWAutomationAfterSend($wAutomation);
        if (!$appliedLog) {
            $wAutomation = $this->wAutomationAfterSendRepository->update($wAutomation, $dto);
            return $wAutomation;
        }

        try {
            DB::beginTransaction();

            // If was applied at least once, I soft-delete it and create a new one.
            $this->wAutomationAfterSendRepository->delete($wAutomation);
            $newAutomation = $this->wAutomationAfterSendRepository->create($dto);
            
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $newAutomation;
    }


    public function apply(WhatsAppSendingMessage $originalWapSendingMsg): ?WAutomationLog
    {
        $wAutomationAfterSend = $this->findOneEnabledByClientId($originalWapSendingMsg->client_id);
        if (!$wAutomationAfterSend || !$wAutomationAfterSend->enabled) {
            return null;
        }

        $existentLog = null;
        if ($wAutomationAfterSend->apply_only_once) {
            // Si debe aplicar una sola vez, busco si ya aplicó alguna vez para este lead.
            $existentLog = $this->wAutomationLogService->findOneAfterSendByLeadId($originalWapSendingMsg->lead_id);
        } else {
            // Si debe aplicar siempre, descarto que no haya aplicado ya para ESTE WapSendingMessage.
            $existentLog = $this->wAutomationLogService->findOneAfterSendByWhatsAppSendingMessage(
                $originalWapSendingMsg
            );
        }
        if ($existentLog) {
            return null;
        }

        
        $exception = $this->getExceptionIfNotEligible($wAutomationAfterSend, $originalWapSendingMsg);
        if ($exception) {
            $wAutomationLog = $this->wAutomationLogService->createWAutomationAfterSendLog(
                $originalWapSendingMsg, $wAutomationAfterSend, $exception
            );
            if (!($exception instanceof WAutomationNotToReportException)) {
                report($exception);
            }
            return null;
        }

        // Fix: me aseguro que el EventDispatcher tenga un user = NULL, para que no registre user incorrecto.
        resolve(TimelineEventsDispatcherService::class)->setLoginUser(null);
        try {
            DB::beginTransaction();

            $this->addLeadNote($originalWapSendingMsg->lead, $wAutomationAfterSend);
            $this->addLeadTags($originalWapSendingMsg->lead, $wAutomationAfterSend);
            $this->removeLeadTags($originalWapSendingMsg->lead, $wAutomationAfterSend);
            $this->assignLeadStatus($originalWapSendingMsg->lead, $wAutomationAfterSend);
            $wAutomationLog = $this->wAutomationLogService->createWAutomationAfterSendLog(
                $originalWapSendingMsg, $wAutomationAfterSend
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $wAutomationLog;
    }


    // Si retorna un WAutomationNotToReportException, es por que no es un error, es algo esperable
    // en el flujo normal: por ende no se reporta a Sentry, pero se guarda la causa en el WAutomationLog.
    public function getExceptionIfNotEligible(
        wAutomationAfterSend $wAutomationAfterSend,
        WhatsAppSendingMessage $originalWapSendingMsg,
    ): Exception | WAutomationNotToReportException | null {
        $originalWapSending = $originalWapSendingMsg->whatsAppSending;
        if (!$originalWapSending) {
            return new Exception('wap_sending_does_not_exist');
        }
        if ($originalWapSending->paused_date) {
            return new Exception('wap_sending_was_paused');
        }
        if ($originalWapSending->cancelled_date) {
            return new Exception('wap_sending_was_cancelled');
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
        if (!$originalWapSendingMsg->success) {
            return new Exception('wap_sending_message_was_not_successfully_sent');
        }
        if (!$originalWapSendingMsg->sent_date) {
            return new Exception('wap_sending_message_was_not_successfully_sent');
        }
        if ($originalWapSendingMsg->paused_date) {
            return new Exception('wap_sending_message_was_paused');
        }
        if ($originalWapSendingMsg->cancelled_date) {
            return new Exception('wap_sending_message_was_cancelled');
        }
        if ($originalWapSendingMsg->wautomation_log_id) {
            return new Exception('wap_sending_message_was_sent_by_an_automation');
        }

        // OJO: Quien marca al user como desviculado ante un error de envío es WAPIService
        // Mirar: en WAPIService -> WAPIHelperUserNotSyncedException. No es necesario hacerlo acá.
        // if (!$originalWapSending->user->wapi_is_synced) {
        //     return new UserWAPINotSyncedException('user_is_not_synced_with_wapi');
        // }
        // if (!$originalWapSending->user->wapi_session_phone_number) {
        //     return new UserWAPINotSyncedException('user_is_not_synced_with_wapi');
        // }
        // if (!$originalWapSending->client->clientSettings->enable_wapi) {
        //     return new UserWAPINotSyncedException('wapi_is_not_enabled');
        // }

        if ($wAutomationAfterSend->only_apply_to_massive_sendings) {
            if (!$originalWapSending->is_massive) {
                return new WAutomationNotToReportException('is_not_a_massive_sending');
            }
            if (!$originalWapSendingMsg->is_massive) {
                return new WAutomationNotToReportException('is_not_a_massive_sending');
            }
        }
        
        return null;
    }


    protected function parametersChanged(WAutomationAfterSend $wAutomation, WAutomationAfterSendDTO $dto): bool
    {
        $dtoStatusIdToAssign = $dto->statusToAssign?->id;
        $wAutomationStatusIdToAssign = $wAutomation->assign_status_id;

        $dtoTagIdsToAddIds = $dto->tagsToAdd->pluck('id')->map(fn ($id) => (int) $id)->values();
        $wAutomationTagIdsToAddIds = $wAutomation->tagsToAdd->pluck('id')->map(fn ($id) => (int) $id)->values();
        
        $dtoTagIdsToRemoveIds = $dto->tagsToRemove->pluck('id')->map(fn ($id) => (int) $id)->values();
        $wAutomationTagIdsToRemoveIds = $wAutomation->tagsToRemove->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($dtoStatusIdToAssign != $wAutomationStatusIdToAssign) {
            return true;
        }
        if ($dtoTagIdsToAddIds != $wAutomationTagIdsToAddIds) {
            return true;
        }
        if ($dtoTagIdsToRemoveIds != $wAutomationTagIdsToRemoveIds) {
            return true;
        }
        if ($wAutomation->add_new_note != $dto->addNewNote) {
            return true;
        }
        if ($wAutomation->new_note_text != $dto->newNoteText) {
            return true;
        }
        if ($wAutomation->enabled != $dto->enabled) {
            return true;
        }
        if ($wAutomation->only_apply_to_massive_sendings != $dto->onlyApplyToMassiveSendings) {
            return true;
        }
        if ($wAutomation->apply_only_once != $dto->applyOnlyOnce) {
            return true;
        }
        return false;
    }


    protected function addLeadNote(Lead $lead, WAutomationAfterSend $wAutomation): Lead
    {
        if (!$wAutomation->add_new_note || !$wAutomation->new_note_text) {
            return $lead;
        }
        $data = [
            'user_id' => $lead->user_id,
            'client_id' => $lead->client_id,
            'text' => $wAutomation->new_note_text,
        ];
        $this->noteService->create($lead, $data);
        return $lead;
    }


    protected function addLeadTags(Lead $lead, WAutomationAfterSend $wAutomation): Lead
    {
        if ($wAutomation->tagsToAdd->isEmpty()) {
            return $lead;
        }
        $this->actionsLeadService->assignTags(
            $lead, $wAutomation->tagsToAdd, ['assignType' => 'add']
        );
        return $lead;
    }


    protected function removeLeadTags(Lead $lead, WAutomationAfterSend $wAutomation): Lead
    {
        if ($wAutomation->tagsToRemove->isEmpty()) {
            return $lead;
        }
        $this->actionsLeadService->assignTags(
            $lead, $wAutomation->tagsToRemove, ['assignType' => 'remove']
        );
        return $lead;
    }


    public function assignLeadStatus(Lead $lead, WAutomationAfterSend $wAutomation): Lead
    {
        if ($wAutomation->statusToAssign) {
            $this->actionsLeadService->changeStatus($lead, $wAutomation->statusToAssign);
        }
        return $lead;
    }

}
