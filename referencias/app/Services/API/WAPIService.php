<?php

namespace App\Services\API;

use Throwable;
use Exception;
use App\Models\User;
use App\Models\Client;
use App\Helpers\WAPIHelper;
use App\Helpers\PhonesHelper;
use App\DTO\WAPI\WAPIChatDTO;
use App\Models\WhatsAppSending;
use App\Models\LeadContactPhone;
use App\Services\API\UserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\DTO\WAPI\WAPIChatMessageDTO;
use App\DTO\WAPI\WAPIMessageModalDTO;
use App\DTO\WAPI\WAPIMessageMediaDTO;
use App\Models\WhatsAppSendingMessage;
use App\DTO\WAPI\WAPIHelperMessageDTO;
use App\Services\Traits\GetUserFromRequest;
use App\Services\API\WhatsAppSendingService;
use App\Services\API\ProposalInfoTmpService;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\LeadContactPhoneService;
use App\DTO\WAPI\WAPINewSendingParametersDTO;
use App\DTO\WAPI\WAPINewWAutomationSendingParametersDTO;
use App\Services\API\Dispatchers\UserEventsDispatcherService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Exceptions\Helpers\WAPIHelper\WAPIHelperUserNotSyncedException;


class WAPIService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        private readonly WhatsAppSendingService $whatsAppSendingService,
        private readonly LeadContactPhoneService $leadContactPhoneService,
        private readonly UserService $userService,
        private readonly WAPIHelper $WAPIHelper,
        private readonly PhonesHelper $phonesHelper,
        private readonly ProposalInfoTmpService $proposalInfoTmpService,
        private readonly UserEventsDispatcherService $userEventsDispatcherService,
        private readonly WhatsAppEventsDispatcherService $whatsAppEventsDispatcherService,
        private readonly TimelineEventsDispatcherService $timelineEventsDispatcherService,
    ) {
    }


    public function listChatMessages(
        LeadContactPhone $leadContactPhone,
        array $opts = []
    ): Collection {
        $leadUser = $leadContactPhone->lead->user;
        if (!$leadUser->wapi_session_phone_number || !$leadUser->wapi_is_synced) {
            throw new Exception('lead_user_is_not_synced_with_wapi');
        }

        $wapiSessionPhoneNumber = $leadUser->wapi_session_phone_number;
        $chatPhoneNumber = $this->phonesHelper->getWhatsAppFormattedPhoneFromLeadContactPhone($leadContactPhone);
        $WAPIChatMessages = $this->WAPIHelper
            ->setRouteAndEngineFromUser($leadUser)
            ->listChatMessages($wapiSessionPhoneNumber, $chatPhoneNumber, ['limit' => $opts['limit'] ?? 100])
        ;
        $WAPIChatMessagesDTOs = collect($WAPIChatMessages)
            ->map(fn ($WAPIChatMessage) => new WAPIChatMessageDTO($WAPIChatMessage))
        ;
        return $WAPIChatMessagesDTOs;
    }


    public function listChats(User $user, array $opts = []): Collection
    {
        $WAPIChats = $this->WAPIHelper
            ->setRouteAndEngineFromUser($user)
            ->listChats($user->wapi_session_phone_number, ['limit' => $opts['limit'] ?? 100])
        ;
        $WAPIChatDTOs = collect($WAPIChats)->map(fn ($WAPIChat) => new WAPIChatDTO($WAPIChat));
        return $WAPIChatDTOs;
    }


    public function getChatMessageMediaInfo(
        LeadContactPhone $leadContactPhone,
        string $wapiChatMessageId
    ): WAPIMessageMediaDTO | null {
        $leadUser = $leadContactPhone->lead->user;
        if (!$leadUser->wapi_session_phone_number || !$leadUser->wapi_is_synced) {
            throw new Exception('lead_user_is_not_synced_with_wapi');
        }

        $mediaInfo = $this->WAPIHelper
            ->setRouteAndEngineFromUser($leadUser)
            ->getChatMessageMediaInfo($leadUser->wapi_session_phone_number, $wapiChatMessageId)
        ;
        if (!($mediaInfo['data'] ?? null)) {
            return null;
        }
        $WAPIMessageMediaDTO = new WAPIMessageMediaDTO(
            data: $mediaInfo['data'],
            mimeType: $mediaInfo['mimetype'],
            fileSize: $mediaInfo['filesize'] ?? null,
        );
        return $WAPIMessageMediaDTO;
    }


    public function getMessageModalInfo(Collection $leadIds): WAPIMessageModalDTO
    {
        $client = $this->getClient();
        $dto = new WAPIMessageModalDTO();
        $dto->leadContactPhones = $this->leadContactPhoneService->findByClientAndLeadIds($client, $leadIds);
        return $dto;
    }


    public function createNewSending(WAPINewSendingParametersDTO $dto): WhatsAppSending
    {
        $this->validateWAPIIsEnabled($this->getClient(), $this->getUser());
        if (!$dto->chatMessage) {
            throw new Exception('wapi_chat_message_do_not_exists');
        }

        $whatsAppSending = $this->whatsAppSendingService->createNewWAPISending($dto);
        
        if ($whatsAppSending->is_proposal) {
            $proposalInfoTmp = $this->proposalInfoTmpService->createNewByWAPSendingAndDTO($whatsAppSending, $dto);
        }
        if (!$dto->isScheduled()) {
            $this->dispatchWhatsAppSendingMessages($whatsAppSending);
        } else {
            $this->timelineEventsDispatcherService->whatsAppSendingMessagesScheduled($whatsAppSending);
        }
        return $whatsAppSending;
    }


    public function dispatchWhatsAppSendingMessages(WhatsAppSending $whatsAppSending): void
    {
        try {
            DB::beginTransaction();
            $this->whatsAppSendingService->markSendingMessagesAsDispatched($whatsAppSending);
            $this->whatsAppEventsDispatcherService->dispatchSendWAPIMessagesJobs($whatsAppSending);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function createNewWAutomationSending(WAPINewWAutomationSendingParametersDTO $dto): WhatsAppSending
    {
        $this->validateWAPIIsEnabled($dto->client, $dto->user);
        if (!$dto->chatMessage) {
            throw new Exception('wapi_chat_message_do_not_exists');
        }

        try {
            DB::beginTransaction();
            $whatsAppSending = $this->whatsAppSendingService->createNewWAPISending($dto);
            $this->whatsAppSendingService->markSendingMessagesAsDispatched($whatsAppSending);
            $this->whatsAppEventsDispatcherService->dispatchSendWAutomationWAPIMessagesJobs($whatsAppSending);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return $whatsAppSending;
    }


    public function sendMessage(WhatsAppSendingMessage $wapSendingMsg): array
    {
        $WAPIHelperDTO = WAPIHelperMessageDTO::buildFromWhatsAppSendingMessage($wapSendingMsg);

        $redirectWapi = config('wapi.redirect_wapi', false);
        $redirectWapiToPhone = config('wapi.redirect_wapi_to_phone', null);
        if ($redirectWapi && $redirectWapiToPhone) {
            $WAPIHelperDTO->phoneNumber = $redirectWapiToPhone;
        }
        $replaceWAPIFromPhone = config('wapi.replace_wapi_from_phone', null);
        if ($redirectWapi && $replaceWAPIFromPhone) {
            $WAPIHelperDTO->wapiSessionPhoneNumber = $replaceWAPIFromPhone;
        }

        try {
            $this->validateWAPIIsEnabled($wapSendingMsg->client, $wapSendingMsg->user);
            
            $WAPIResponse = $this->WAPIHelper
                ->setRouteAndEngineFromUser($wapSendingMsg->user)
                ->sendMessage($WAPIHelperDTO)
            ;
            $this->whatsAppSendingService->markMessageAsSent($wapSendingMsg, true);
        } catch (Throwable $e) {
            $notAuth = stripos($e->getMessage(), 'auth_session_does_not_exist') !== false;
            $notAuth = $notAuth || stripos($e->getMessage(), 'whatsapp_client_session_not_authenticated') !== false;
            if ($notAuth || $e instanceof WAPIHelperUserNotSyncedException) {
                // Si en WAPI figura como no vinculado, le deshabilito WAPI en Clienty durante 5 minutos, luego lo
                // habilito nuevamente. Con esto evito que si hay automations o mensajes programados, ralenticen
                // la queue (ya que verificar vinculación tarda unos segundos en WAPI) jodiendo a otros
                // jobs que necesitan correr.
                $this->userService->update($wapSendingMsg->user, ['wapi_is_synced' => false]);
                // $this->userEventsDispatcherService->dispatchDisableUserWAPIJob($wapSendingMsg->user);
                // $this->userEventsDispatcherService->dispatchEnableUserWAPIJob($wapSendingMsg->user, 300);
            }
            $this->whatsAppSendingService->markMessageAsSent($wapSendingMsg, false, $e->getMessage());
            throw $e;
        }
        return $WAPIResponse;
    }


    public function deleteSessionFiles(string $phoneNumber): bool
    {
        $WAPIResponse = $this->WAPIHelper
            ->setRouteAndEngineFromUser($this->getUser())
            ->deleteSessionFiles($phoneNumber)
        ;
        return $WAPIResponse;
    }


    protected function validateWAPIIsEnabled(Client $client, User $user): void
    {
        if (!$client->clientSettings->enable_wapi) {
            throw new Exception('wapi_is_not_enabled');
        }
        if (!$user->wapi_session_phone_number) {
            throw new Exception('user_is_not_synced_with_wapi');
        }
        if (!$user->wapi_is_synced) {
            throw new Exception('user_is_not_synced_with_wapi');
        }
    }
 
}
