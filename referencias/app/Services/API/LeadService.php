<?php

namespace App\Services\API;

use DateTime;
use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\WapBot;
use App\Models\Client;
use App\Models\LeadContactPhone;
use App\DTO\Import\LeadsLeadDTO;
use App\Models\AutomationNewLead;
use App\Services\API\NoteService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\DTO\CreateNewManualLeadDTO;
use App\Services\API\LandingService;
use App\Repositories\LeadRepository;
use App\DTO\WapBot\NewWapBotLeadDTO;
use App\Exceptions\DatabaseException;
use App\Services\Traits\GetUserFromRequest;
use App\Services\API\LeadCustomFieldService;
use App\Services\Traits\GetClientFromRequest;
use Exceptions\Models\LeadBuildHashException;
use App\Services\API\LeadContactEmailService;
use App\Services\API\LeadContactPhoneService;
use App\Services\Traits\StoresExistentInstance;
use App\Models\MongoDB\WapBot\WapBotConversation;
use App\DTO\Integration\UpdateIntegrationLeadDTO;
use App\Services\API\LeadNotificationEmailService;
use App\DTO\Integration\CreateNewIntegrationLeadDTO;
use App\DTO\Import\BulkUpload\BulkUploadLeadDataDTO;
use App\DTO\Import\BulkUpdate\BulkUpdateLeadDataDTO;
use App\DTO\FacebookPage\ClientFacebookPageLeadInfoDTO;
use App\DTO\Integration\UpdateIntegrationMakeAppLeadDTO;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;
use App\DTO\Integration\UpdateIntegrationZapierAppLeadDTO;
use App\Services\API\Automations\AutomationNewLeadService;
use App\DTO\Integration\CreateNewIntegrationMakeAppLeadDTO;
use App\Services\API\LeadNotificationWhatsAppMessageService;
use App\Exceptions\Services\LeadService\UpdateLeadException;
use App\DTO\Integration\CreateNewIntegrationZapierAppLeadDTO;
use App\Services\API\Dispatchers\LeadEventsDispatcherService;
use App\Exceptions\Services\LeadService\ExistentLeadException;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Services\API\Dispatchers\SearchLeadEventsDispatcherService;
use App\Exceptions\Services\LeadService\FacebookInvalidLeadException;
use App\Services\API\Dispatchers\GoogleContactsEventsDispatcherService;
use App\Services\API\Dispatchers\IntegrationAPIEventsDispatcherService;


class LeadService
{

    use GetClientFromRequest, GetUserFromRequest, StoresExistentInstance;

    private $noteService;
    private $userService;
    private $statusService;
    private $landingService;
    private $leadRepository;
    private $leadContactService;
    private $automationLogService;
    private $leadCustomFieldService;
    private $browserEventsDispatcher;
    private $leadContactEmailService;
    private $leadContactPhoneService;
    private $automationNewLeadService;
    private $acquisitionChannelService;
    private $leadEventsDispatcherService;
    private $leadNotificationEmailService;
    private $timelineEventsDispatcherService;
    private $searchLeadEventsDispatcherService;
    private $googleContactsEventsDispatcherService;
    private $integrationAPIEventsDispatcherService;
    private $leadNotificationWhatsAppMessageService;


    // Required setters (not in constructor to avoid circular injection)
    public function setAutomationNewLeadService(AutomationNewLeadService $service): LeadService
    {
        $this->automationNewLeadService = $service;
        return $this;
    }


    public function setLeadNotificationEmailService(LeadNotificationEmailService $service): LeadService
    {
        $this->leadNotificationEmailService = $service;
        return $this;
    }


    public function setLeadNotificationWhatsAppMessageService(
        LeadNotificationWhatsAppMessageService $service
    ): LeadService {
        $this->leadNotificationWhatsAppMessageService = $service;
        return $this;
    }


    public function setUserService(UserService $userService): LeadService
    {
        $this->userService = $userService;
        return $this;
    }


    public function __construct(
        LeadRepository $leadRepository,
        LeadContactService $leadContactService,
        LandingService $landingService,
        NoteService $noteService,
        StatusService $statusService,
        AcquisitionChannelService $acquisitionChannelService,
        LeadCustomFieldService $leadCustomFieldService,
        BrowserEventsDispatcher $browserEventsDispatcher,
        LeadContactEmailService $leadContactEmailService,
        LeadContactPhoneService $leadContactPhoneService,
        LeadEventsDispatcherService $leadEventsDispatcherService,
        TimelineEventsDispatcherService $timelineEventsDispatcherService,
        SearchLeadEventsDispatcherService $searchLeadEventsDispatcherService,
        GoogleContactsEventsDispatcherService $googleContactsEventsDispatcherService,
        IntegrationAPIEventsDispatcherService $integrationAPIEventsDispatcherService
    ) {
        $this->noteService = $noteService;
        $this->statusService = $statusService;
        $this->leadRepository = $leadRepository;
        $this->landingService = $landingService;
        $this->leadContactService = $leadContactService;
        $this->leadCustomFieldService = $leadCustomFieldService;
        $this->browserEventsDispatcher = $browserEventsDispatcher;
        $this->leadContactEmailService = $leadContactEmailService;
        $this->leadContactPhoneService = $leadContactPhoneService;
        $this->acquisitionChannelService = $acquisitionChannelService;
        $this->leadEventsDispatcherService = $leadEventsDispatcherService;
        $this->timelineEventsDispatcherService = $timelineEventsDispatcherService;
        $this->searchLeadEventsDispatcherService = $searchLeadEventsDispatcherService;
        $this->googleContactsEventsDispatcherService = $googleContactsEventsDispatcherService;
        $this->integrationAPIEventsDispatcherService = $integrationAPIEventsDispatcherService;
        $this->setExistentInstance($this);
    }


    public function find(int $id, array $opts = []): ?Lead
    {
        $failIfNotExists = $opts['failIfNotExists'] ?? true;
        if ($failIfNotExists) {
            return Lead::findOrFail($id);
        }
        return Lead::find($id);
    }


    /**
     * Dado un array de números de teléfono (formato WhatsApp, ej: "5491159711575"),
     * devuelve un mapa [phoneNumber => [leads]] con los leads asociados vía normalized_hash.
     * Máximo $limitPerPhone leads por número.
     */
    public function findLeadsByNormalizedPhones(
        Client $client,
        array $phoneNumbers,
        int $limitPerPhone = 5
    ): array {
        if (empty($phoneNumbers)) {
            return [];
        }

        $hashes = array_map(fn ($phone) => LeadContactPhone::buildNormalizedHash($phone), $phoneNumbers);
        $hashToPhone = array_combine($hashes, $phoneNumbers);

        $leadContactPhones = LeadContactPhone::where('client_id', $client->id)
            ->whereIn('normalized_hash', $hashes)
            ->select('id', 'lead_id', 'normalized_hash')
            ->get();

        $leadIds = $leadContactPhones->pluck('lead_id')->unique()->values();
        if ($leadIds->isEmpty()) {
            return [];
        }

        $leads = Lead::whereIn('id', $leadIds)
            ->with(['status', 'user', 'tags', 'leadContacts.leadContactPhones', 'leadContacts.leadContactEmails'])
            ->get()
            ->keyBy('id');

        // Agrupar por phone number
        $result = [];
        foreach ($leadContactPhones as $lcp) {
            $phone = $hashToPhone[$lcp->normalized_hash] ?? null;
            if (!$phone) {
                continue;
            }
            $lead = $leads->get($lcp->lead_id);
            if (!$lead) {
                continue;
            }
            if (!isset($result[$phone])) {
                $result[$phone] = [];
            }
            // Evitar duplicados (un lead puede tener múltiples phones con el mismo normalized_hash)
            $alreadyAdded = collect($result[$phone])->pluck('id')->contains($lead->id);
            if (!$alreadyAdded && count($result[$phone]) < $limitPerPhone) {
                $result[$phone][] = $lead;
            }
        }

        return $result;
    }


    public function findByIdsAndStatusList(Collection $leadIds, Collection $statusList): Collection
    {
        $statusIds = $statusList->pluck('id');
        return Lead::whereIn('id', $leadIds)->whereIn('status_id', $statusIds)->get();
    }


    public function findByIds(Collection $leadIds, array $opts = []): Collection
    {
        return $this->leadRepository->findByIds($leadIds, $opts);
    }


    public function findByClientAndIds(Client $client, Collection $leadIds, array $opts = []): Collection
    {
        return $this->leadRepository->findByClientAndIds($client, $leadIds, $opts);
    }


    public function findOneByLeadsId(int $leadsLeadId, array $opts = []): ?Lead
    {
        return $this->leadRepository->findOneByLeadsId($leadsLeadId, $opts);
    }


    public function findLastLeadByClient(Client $client): ?Lead
    {
        return $this->leadRepository->findLastLeadByClient($client);
    }


    public function findOneByClientAndHash(Client $client, string $hash, array $opts = []): ?Lead
    {
        return $this->leadRepository->findOneByClientAndHash($client, $hash, $opts);
    }


    public function findByClientAndHash(Client $client, string $hash, array $opts = []): Collection
    {
        return $this->leadRepository->findByClientAndHash($client, $hash, $opts);
    }


    public function findByClientWithNoTags(Client $client, array $opts = []): Collection
    {
        return $this->leadRepository->findByClientWithNoTags($client, $opts);
    }


    public function createManual(CreateNewManualLeadDTO $dto): Lead
    {
        $leadAttrs = $dto->getNewLeadAttrs();
        $leadAttrs['method'] = 'form';
        $leadAttrs['is_manually_created'] = true;
        $leadAttrs['lead_created_at'] = new DateTime();
        $leadAttrs['client_id'] = $this->getClient()->id;
        $mainLeadContactAttrs = $dto->getMainLeadContactAttrs();
        $mainLeadContactAttrs['is_main'] = true;
        $leadAttrs['hash'] = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
        
        try {
            DB::beginTransaction();
            
            $newLead = $this->leadRepository->store($leadAttrs);
            
            $mainLeadContact = $this->leadContactService->create(
                $newLead, $mainLeadContactAttrs, ['isNewLead' => true, 'useTransaction' => false]
            );
            
            $this->noteService->createMultipleForOneLead($newLead, $dto->getNotes(), $this->getUser());

            if ($dto->getTags()->isNotEmpty()) {
                $newLead->tags()->saveMany($dto->getTags());
            }
        
            if ($dto->getCustomFields()->isNotEmpty()) {
                foreach ($dto->getCustomFields() as $customField) {
                    $leadCustomField = $customField['leadCustomField'];
                    $this->leadCustomFieldService->setValue($newLead, $leadCustomField, $customField['value']);
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $newLead->refresh();
        $this->browserEventsDispatcher->notifyNewManualLead($newLead);
        $this->dispatchSendNewLeadDataToWebhookJobIfEnabled($newLead);
        $this->timelineEventsDispatcherService->leadManuallyCreated($newLead);
        $this->leadEventsDispatcherService->dispatchApplyNewLeadAutomationJob($newLead);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($newLead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncNewLeadWithGoogleContactsJob($newLead);

        return $newLead;
    }


    public function createBulkManual(BulkUploadLeadDataDTO $leadDTO): Lead
    {
        $client = $this->getClient();
        $leadAttrs = $leadDTO->getNewLeadAttrs();
        $leadAttrs['method'] = 'form';
        $leadAttrs['is_bulk_created'] = true;
        $leadAttrs['client_id'] = $client->id;
        $leadAttrs['is_manually_created'] = true;
        $leadAttrs['lead_created_at'] = new DateTime();

        if (!$leadAttrs['user_id']) {
            $user = $this->userService->findUserToAssign($client);
            $leadAttrs['user_id'] = $user->id;
        }

        $mainLeadContactAttrs = $leadDTO->getMainLeadContactAttrs();
        $secondaryContactsAttrs = $leadDTO->getSecondaryLeadContactsAttrs();

        $hash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
        $existentLead = $this->findOneByClientAndHash($this->getClient(), $hash);
        if ($existentLead) {
            $e = (new ExistentLeadException())->setLead($existentLead);
            throw $e;
        }

        try {
            DB::beginTransaction();

            $leadAttrs['hash'] = $hash;
            $newLead = $this->leadRepository->store($leadAttrs);

            $mainLeadContact = $this->leadContactService->create(
                $newLead, $mainLeadContactAttrs, ['isNewLead' => true, 'useTransaction' => false]
            );
            foreach ($secondaryContactsAttrs as $contact) {
                $this->leadContactService->create(
                    $newLead, $contact, ['isNewLead' => true, 'useTransaction' => false],
                );
            }

            if ($leadDTO->getTags()) {
                $newLead->tags()->saveMany($leadDTO->getTags());
            }
            if ($leadDTO->notes) {
                $noteData = [
                    'user_id' => $newLead->user_id, 'client_id' => $newLead->client_id, 'text' => $leadDTO->notes
                ];
                $this->noteService->create($newLead, $noteData);
            }

            foreach ($leadDTO->getCustomFieldsDTOs() as $customFieldDTO) {
                $this->leadCustomFieldService->setValue(
                    $newLead, $customFieldDTO->leadCustomField, $customFieldDTO->value
                );
            };

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->dispatchSendNewLeadDataToWebhookJobIfEnabled($newLead);
        $this->timelineEventsDispatcherService->leadManuallyCreated($newLead);
        $this->leadEventsDispatcherService->dispatchApplyNewLeadAutomationJob($newLead);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($newLead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncNewLeadWithGoogleContactsJob($newLead);

        return $newLead->fresh();
    }


    public function persistLeadsImportedLead(Client $client, LeadsLeadDTO $leadsLeadDTO): Lead
    {
        $landingAttrs = $leadsLeadDTO->getLandingAttrs();
        $landing = $this->landingService->findOrCreateByClientAndUrl(
            $client, $landingAttrs['url'], ['leads_landing_id' => $landingAttrs['leads_landing_id']]
        );

        $channelAttrs = $leadsLeadDTO->getAcquisitionChannelAttrs();
        $channel = $channelAttrs['name']
            ? $this->acquisitionChannelService->findOrCreateByClientAndName($client, $channelAttrs['name'])
            : null
        ;

        $leadAttrs = $leadsLeadDTO->getLeadAttrs();
        $mainLeadContactAttrs = $leadsLeadDTO->getMainLeadContactAttrs();
        $mainLeadContactAttrs['client_id'] = $client->id;

        $user = $this->userService->findUserToAssign($client);
        $status = $this->statusService->findOrCreateStatusNew($client);

        $leadAttrs['user_id'] = $user->id;
        $leadAttrs['client_id'] = $client->id;
        $leadAttrs['status_id'] = $status->id;
        $leadAttrs['landing_id'] = $landing->id;
        $leadAttrs['acquisition_channel_id'] = $channel ? $channel->id : null;
        $leadAttrs['hash'] = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);

        try {
            DB::beginTransaction();
            $newLead = $this->leadRepository->store($leadAttrs);
            $this->leadContactService->create(
                $newLead, $mainLeadContactAttrs, ['isNewLead' => true, 'useTransaction' => false]
            );
            $this->leadCustomFieldService->createDefaultValues($newLead);
            $this->leadNotificationEmailService->createNewDefault($newLead);
            $this->leadNotificationWhatsAppMessageService->createNewDefault($newLead);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $newLead->refresh();

        $this->dispatchSendNewLeadDataToWebhookJobIfEnabled($newLead);
        $this->leadEventsDispatcherService->dispatchApplyNewLeadAutomationJob($newLead);

        // Delay 15 seconds, so dispatchApplyNewLeadAutomationJob can run first.
        $this->leadEventsDispatcherService->dispatchSendNewLeadEmailJob($newLead, 15);
        $this->leadEventsDispatcherService->dispatchSendNewLeadWhatsAppMessageJob($newLead, 15);
        
        $this->timelineEventsDispatcherService->leadCreated($newLead);
        $this->browserEventsDispatcher->notifyNewLead($newLead);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($newLead->id);
        if ($client->clientSettings->enable_google_contacts_api) {
            $this->googleContactsEventsDispatcherService->dispatchSyncNewLeadWithGoogleContactsJob($newLead);
        }

        return $newLead;
    }


    public function createFromApiIntegration(CreateNewIntegrationLeadDTO $dto): Lead
    {
        $client = $this->getClient();
        $leadAttrs = $dto->getLeadAttrs();
        
        $landingUrl = $dto->getLandingUrl();
        $customFields = $dto->getCustomFields();
        $user = $this->userService->findUserToAssign($client);
        $mainLeadContactAttrs = $dto->getMainLeadContactAttrs();
        $status = $this->statusService->findOrCreateStatusNew($client);
        
        $leadAttrs['user_id'] = $user->id;
        $leadAttrs['status_id'] = $status->id;
        $leadAttrs['lead_created_at'] = new DateTime();
        $leadAttrs['hash'] = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
        if ($landingUrl) {
            $landing = $this->landingService->findOrCreateByClientAndUrl($client, $landingUrl);
            $leadAttrs['landing_id'] = $landing->id;
        }

        try {
            DB::beginTransaction();

            $newLead = $this->leadRepository->store($leadAttrs);

            $this->leadContactService->create(
                $newLead, $mainLeadContactAttrs, ['isNewLead' => true, 'useTransaction' => false]
            );

            $this->leadCustomFieldService->createDefaultValues($newLead);

            if ($customFields->isNotEmpty()) {
                foreach ($customFields as $customField) {
                    $leadCustomField = $customField['leadCustomField'];
                    $this->leadCustomFieldService->setValue($newLead, $leadCustomField, $customField['value']);
                }
            }

            $notes = $dto->getNotes();
            if ($notes->isNotEmpty()) {
                $this->noteService->createMultipleForOneLead($newLead, $notes);
            }

            
            $this->leadNotificationEmailService->createNewDefault($newLead);
            $this->leadNotificationWhatsAppMessageService->createNewDefault($newLead);
            
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->leadEventsDispatcherService->dispatchApplyNewLeadAutomationJob($newLead);
        
        // Delay 15 seconds, so dispatchApplyNewLeadAutomationJob/dispatchApplyNewLeadWhatsAppMessageJob can run first.
        $this->leadEventsDispatcherService->dispatchSendNewLeadEmailJob($newLead, 15);
        $this->leadEventsDispatcherService->dispatchSendNewLeadWhatsAppMessageJob($newLead, 15);
            
        $this->timelineEventsDispatcherService->leadCreated($newLead);
        $this->browserEventsDispatcher->notifyNewLead($newLead);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($newLead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncNewLeadWithGoogleContactsJob($newLead);

        return $newLead->fresh();
    }


    public function createFromWapBot(WapBot $wapBot, WapBotConversation $wapBotConversation): Lead
    {
        $dto = new NewWapBotLeadDTO($wapBot, $wapBotConversation);
        
        $leadAttrs = $dto->getLeadAttrs();
        $mainLeadContactAttrs = $dto->getMainLeadContactAttrs();
        $hash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);

        $existentLead = $this->findOneByClientAndHash($wapBot->client, $hash);
        if ($existentLead) {
            $existentLead->alreadyExists = true; // hack para saber si existía ya
            return $existentLead;
        }

        try {
            DB::beginTransaction();

            $status = $this->statusService->findOrCreateStatusNew($wapBot->client);
            
            $leadAttrs['hash'] = $hash;
            $leadAttrs['status_id'] = $status->id;
            $leadAttrs['lead_created_at'] = new DateTime();
            $mainLeadContactAttrs['client_id'] = $wapBot->client->id;
            if (!($leadAttrs['user_id'] ?? null)) {
                $user = $this->userService->findUserToAssign($wapBot->client);
                $leadAttrs['user_id'] = $user->id;
            }

            $newLead = $this->leadRepository->store($leadAttrs);
            $mainLeadContact = $this->leadContactService->create(
                $newLead, $mainLeadContactAttrs, ['isNewLead' => true, 'useTransaction' => false]
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->dispatchSendNewLeadDataToWebhookJobIfEnabled($newLead);
        $this->timelineEventsDispatcherService->leadCreated($newLead);
        $this->leadEventsDispatcherService->dispatchApplyNewLeadAutomationJob($newLead);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($newLead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncNewLeadWithGoogleContactsJob($newLead);

        return $newLead->fresh();
    }


    public function updateFromApiIntegration(
        Lead $lead,
        UpdateIntegrationLeadDTO | UpdateIntegrationMakeAppLeadDTO | UpdateIntegrationZapierAppLeadDTO $dto
    ): Lead {
        $leadAttrs = $dto->getLeadAttrs();
        $customFields = $dto->getCustomFields();
        $leadContactAttrs = $dto->getLeadContactAttrs();

        $firstEmail = $dto->getLeadFirstEmail();
        $secondEmail = $dto->getLeadSecondEmail();
        if ($secondEmail && !$firstEmail) {
            $firstEmail = $secondEmail;
            $secondEmail = null;
        }
        $firstEmailExists = $firstEmail && $lead->leadContactEmails->pluck('email')->contains($firstEmail);
        $secondEmailExists = $secondEmail && $lead->leadContactEmails->pluck('email')->contains($secondEmail);

        $firstPhone = $dto->getLeadFirstPhone();
        $secondPhone = $dto->getLeadSecondPhone();
        if ($secondPhone && !$firstPhone) {
            $firstPhone = $secondPhone;
            $secondPhone = null;
        }
        $firstPhoneExists = $firstPhone && $lead->leadContactPhones->pluck('phone')->contains($firstPhone);
        $secondPhoneExists = $secondPhone && $lead->leadContactPhones->pluck('phone')->contains($secondPhone);
        
        try {
            DB::beginTransaction();

            if ($leadAttrs) {
                $this->update($lead, $leadAttrs);
            }
            if ($leadContactAttrs) {
                $this->leadContactService->update($lead->mainLeadContact, $leadContactAttrs);
            }

            if ($customFields->isNotEmpty()) {
                foreach ($customFields as $customFieldData) {
                    $leadCustomField = $customFieldData['leadCustomField'];
                    $this->leadCustomFieldService->setValue($lead, $leadCustomField, $customFieldData['value']);
                }
            }

            if ($firstEmail && !$firstPhoneExists) {
                $firstLeadContactEmail = $lead->mainLeadContact->leadContactEmails->first();
                if ($firstLeadContactEmail) {
                    $this->leadContactEmailService->update($firstLeadContactEmail, ['email' => $firstEmail]);
                } else {
                    $this->leadContactEmailService->create($lead->mainLeadContact, ['email' => $firstEmail]);
                }
            }
            if ($secondEmail && !$secondPhoneExists) {
                $secondLeadContactEmail = $lead->mainLeadContact->leadContactEmails->get(1);
                if ($secondLeadContactEmail) {
                    $this->leadContactEmailService->update($secondLeadContactEmail, ['email' => $secondEmail]);
                } else {
                    $this->leadContactEmailService->create($lead->mainLeadContact, ['email' => $secondEmail]);
                }
            }
            if ($firstPhone && !$firstPhoneExists) {
                $firstLeadContactPhone = $lead->mainLeadContact->leadContactPhones->first();
                if ($firstLeadContactPhone) {
                    $this->leadContactPhoneService->update($firstLeadContactPhone, ['phone' => $firstPhone]);
                } else {
                    $this->leadContactPhoneService->create($lead->mainLeadContact, ['phone' => $firstPhone]);
                }
            }
            if ($secondPhone && !$secondPhoneExists) {
                $secondLeadContactPhone = $lead->mainLeadContact->leadContactPhones->get(1);
                if ($secondLeadContactPhone) {
                    $this->leadContactPhoneService->update($secondLeadContactPhone, ['phone' => $secondPhone]);
                } else {
                    $this->leadContactPhoneService->create($lead->mainLeadContact, ['phone' => $secondPhone]);
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $lead;
    }


    public function createFromExternalIntegrationApp(
        CreateNewIntegrationZapierAppLeadDTO | CreateNewIntegrationMakeAppLeadDTO $dto
    ): Lead {
        $client = $this->getClient();
        $landingUrl = $dto->getLandingUrl();
        $user = $this->userService->findUserToAssign($client);
        $mainLeadContactAttrs = $dto->getMainLeadContactAttrs();
        $status = $this->statusService->findOrCreateStatusNew($client);
         
        $leadAttrs = $dto->getLeadAttrs();
        $leadAttrs['method'] = 'form';
        $leadAttrs['user_id'] = $user->id;
        $leadAttrs['client_id'] = $client->id;
        $leadAttrs['status_id'] = $status->id;
        $leadAttrs['lead_created_at'] = new DateTime();
        $leadAttrs['hash'] = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
        if ($landingUrl) {
            $landing = $this->landingService->findOrCreateByClientAndUrl($client, $landingUrl);
            $leadAttrs['landing_id'] = $landing->id;
        }
        
        try {
            DB::beginTransaction();
            
            $newLead = $this->leadRepository->store($leadAttrs);

            $this->leadContactService->create(
                $newLead, $mainLeadContactAttrs, ['isNewLead' => true, 'useTransaction' => false]
            );

            $this->leadCustomFieldService->createDefaultValues($newLead);

            foreach ($dto->getCustomFields() as $customFieldData) {
                $leadCustomField = $customFieldData['leadCustomField'];
                $this->leadCustomFieldService->setValue($newLead, $leadCustomField, $customFieldData['value']);
            }

            $this->noteService->createMultipleForOneLead($newLead, $dto->getNotes());
            $this->leadNotificationEmailService->createNewDefault($newLead);
            $this->leadNotificationWhatsAppMessageService->createNewDefault($newLead);
            
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->leadEventsDispatcherService->dispatchApplyNewLeadAutomationJob($newLead);

        // Delay 15 seconds, so dispatchApplyNewLeadAutomationJob can run first.
        $this->leadEventsDispatcherService->dispatchSendNewLeadEmailJob($newLead, 15);
        $this->leadEventsDispatcherService->dispatchSendNewLeadWhatsAppMessageJob($newLead, 15);

        $this->timelineEventsDispatcherService->leadCreated($newLead);
        $this->browserEventsDispatcher->notifyNewLead($newLead);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($newLead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncNewLeadWithGoogleContactsJob($newLead);
        $this->dispatchSendNewLeadDataToWebhookJobIfEnabled($newLead);
        
        return $newLead->fresh();
    }


    public function createFromFacebookLeadDTO(ClientFacebookPageLeadInfoDTO $fbLeadDTO): Lead
    {
        $client = $fbLeadDTO->clientFacebookPage->client;
        $acquisitionChannel = $this->acquisitionChannelService->findOrCreateByClientAndName($client, 'Facebook');

        // set client in services
        $this->leadContactService->setClient($client);
        $this->leadNotificationEmailService->setClient($client);

        $leadAttrs = [];
        $leadAttrs = $fbLeadDTO->toArray();
        $leadAttrs['method'] = 'form';
        $leadAttrs['client_id'] = $client->id;
        $leadAttrs['is_facebook_form'] = true;
        $leadAttrs['lead_created_at'] = new DateTime();
        $leadAttrs['acquisition_channel_id'] = $acquisitionChannel->id;

        $user = $this->userService->findUserToAssign($client);
        $status = $this->statusService->findOrCreateStatusNew($client);

        // set user in lead notification service
        $this->leadNotificationEmailService->setRequestUser($user);

        $leadAttrs['user_id'] = $user->id;
        $leadAttrs['status_id'] = $status->id;

        //create main lead contact
        $mainLeadContactAttrs = [
            'email' => $leadAttrs['email'] ?: null,
            'name' => $leadAttrs['name'] ?: null,
            'phone' => $leadAttrs['phone'] ?: null,
            'last_name' => $leadAttrs['last_name'] ?: null
        ];

        try {
            $hash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs, ['isFacebookForm' => true]);
        } catch (LeadBuildHashException $e) {
            throw new FacebookInvalidLeadException($e->getMessage());
        }

        $existentLead = $this->findOneByClientAndHash($client, $hash);

        if ($existentLead) {
            throw (new ExistentLeadException('lead_already_exists'))->setLead($existentLead);
        }

        $leadAttrs['hash'] = $hash;

        try {
            DB::beginTransaction();
            $newLead = $this->leadRepository->store($leadAttrs);
            $this->leadContactService->create(
                $newLead, $mainLeadContactAttrs, ['isNewLead' => true, 'useTransaction' => false]
            );
            $this->leadCustomFieldService->createDefaultValues($newLead);
            $this->leadNotificationEmailService->createNewDefault($newLead);
            $this->leadNotificationWhatsAppMessageService->createNewDefault($newLead);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->dispatchSendNewLeadDataToWebhookJobIfEnabled($newLead);
        $this->leadEventsDispatcherService->dispatchApplyNewLeadAutomationJob($newLead);

        // Delay 15 seconds, so dispatchApplyNewLeadAutomationJob can run first.
        $this->leadEventsDispatcherService->dispatchSendNewLeadEmailJob($newLead, 15);
        $this->leadEventsDispatcherService->dispatchSendNewLeadWhatsAppMessageJob($newLead, 15);

        $this->timelineEventsDispatcherService->leadCreated($newLead);
        $this->browserEventsDispatcher->notifyNewLead($newLead);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($newLead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncNewLeadWithGoogleContactsJob($newLead);

        return $newLead;
    }


    public function update(Lead $lead, array $leadAttrs): Lead
    {
        $updatedLead = $this->leadRepository->update($lead, $leadAttrs);
        $this->timelineEventsDispatcherService->leadUpdated($lead, $leadAttrs);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
        return $updatedLead;
    }


    protected function dispatchSendNewLeadDataToWebhookJobIfEnabled(Lead $newLead): void
    {
        $clientSettings = $newLead->client->clientSettings;
        if (!$clientSettings->enable_integration_api) {
            return;
        }
        
        $webhookUrl = $clientSettings->lead_create_trigger_webhook;
        $integrationAPIDispatcher = $this->integrationAPIEventsDispatcherService;
        if ($webhookUrl) {
            $integrationAPIDispatcher->dispatchSendNewLeadDataToWebhookJob($newLead, $webhookUrl);
        }
        
        $zapierWebhookUrl = $clientSettings->lead_create_trigger_zapier_webhook;
        if ($zapierWebhookUrl) {
            $integrationAPIDispatcher->dispatchSendNewLeadDataToWebhookJob($newLead, $zapierWebhookUrl);
        }
        
        $makeWebhookUrl = $clientSettings->lead_create_trigger_make_webhook;
        if ($makeWebhookUrl) {
            $integrationAPIDispatcher->dispatchSendNewLeadDataToWebhookJob($newLead, $makeWebhookUrl);
        }
    }

}
