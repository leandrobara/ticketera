<?php

namespace App\Services\API\Actions;

use DateTime;
use Throwable;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\User;
use App\Models\Status;
use App\Services\API\TagService;
use App\Services\Traits\GetRealIP;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Repositories\LeadRepository;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Services\Traits\StoresExistentInstance;
use App\Services\API\LeadNotificationEmailService;
use App\Services\API\Dispatchers\LeadEventsDispatcherService;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Services\API\Dispatchers\GoogleContactsEventsDispatcherService;
use App\Services\API\Dispatchers\IntegrationAPIEventsDispatcherService;


class LeadService
{

    use GetClientFromRequest, GetUserFromRequest, GetRealIP, StoresExistentInstance;

    private $tagService;
    private $leadRepository;
    private $leadEventsDispatcherService;
    private $leadNotificationEmailService;
    private $clientEventsDispatcherService;
    private $timelineEventsDispatcherService;
    private $googleContactsEventsDispatcherService;
    private $integrationAPIEventsDispatcherService;


    public function __construct(
        LeadRepository $leadRepository,
        TagService $tagService,
        LeadEventsDispatcherService $leadEventsDispatcherService,
        ClientEventsDispatcherService $clientEventsDispatcherService,
        TimelineEventsDispatcherService $timelineEventsDispatcherService,
        GoogleContactsEventsDispatcherService $googleContactsEventsDispatcherService,
        IntegrationAPIEventsDispatcherService $integrationAPIEventsDispatcherService
    ) {
        $this->tagService = $tagService;
        $this->leadRepository = $leadRepository;
        $this->leadEventsDispatcherService = $leadEventsDispatcherService;
        $this->clientEventsDispatcherService = $clientEventsDispatcherService;
        $this->timelineEventsDispatcherService = $timelineEventsDispatcherService;
        $this->googleContactsEventsDispatcherService = $googleContactsEventsDispatcherService;
        $this->integrationAPIEventsDispatcherService = $integrationAPIEventsDispatcherService;
        $this->setExistentInstance($this);
    }


    public function setLeadNotificationEmailService(LeadNotificationEmailService $service): LeadService
    {
        $this->leadNotificationEmailService = $service;
        return $this;
    }


    public function changeAcquisitionChannel(Lead $lead, AcquisitionChannel $acquisitionChannel): AcquisitionChannel
    {
        $oldAcquisitionChannel = $lead->acquisitionChannel ? $lead->acquisitionChannel->toArray() : null;
        $lead->acquisition_channel_id = $acquisitionChannel->id;
        $lead->saveOrFail();
        
        $this->timelineEventsDispatcherService->leadAcquisitionChannelUpdated(
            $lead->id, $oldAcquisitionChannel, $lead->fresh('acquisitionChannel')->acquisitionChannel
        );
        return $acquisitionChannel;
    }


    public function changeUser(Lead $lead, User $user): User
    {
        $lead->user_id = $user->id;
        $lead->saveOrFail();
        return $user;
    }


    public function changeUserAndDispatchPostEvents(Lead $lead, User $user): User
    {
        $oldUser = $lead->user->toArray();
        $this->changeUser($lead, $user);
        $this->timelineEventsDispatcherService->leadUserUpdated($lead, $oldUser, $user);

        $this->leadNotificationEmailService->createLeadUserChangeDefault($lead);
        $this->leadEventsDispatcherService->dispatchLeadUserChangeEmailJob($lead, $oldUser['id']);

        $this->googleContactsEventsDispatcherService->dispatchSyncChangedUserLeadWithGoogleContactsJob(
            $lead, $oldUser['id']
        );

        return $user->fresh();
    }


    public function setMassiveLeadsUser(array $leadIds, User $newUser): bool
    {
        $loginUser = $this->getUser();
        $leadIdsChunks = collect($leadIds)->chunk(100);
        foreach ($leadIdsChunks as $leadIdsChunk) {
            $leadIdsChunkArr = $leadIdsChunk->toArray();
            $this->leadEventsDispatcherService->dispatchMultipleLeadUserChangeJob(
                $leadIdsChunkArr, $newUser, $loginUser
            );
        }
        return true;
    }


    public function assignInvalidEmailTag(Lead $lead): Lead
    {
        $opts = ['assignType' => 'add'];
        $tag = $this->tagService->getOrCreateInvalidEmailTag($lead->client);
        return $this->assignTag($lead, $tag, $opts);
    }


    public function assignUnsubscribedEmailTag(Lead $lead): Lead
    {
        $opts = ['assignType' => 'add'];
        $tag = $this->tagService->getOrCreateUnsubscribedEmailTag($lead->client);
        return $this->assignTag($lead, $tag, $opts);
    }


    public function assignTag(Lead $lead, Tag $tag, array $opts = []): Lead
    {
        $tags = collect([$tag]);
        $this->assignTags($lead, $tags, $opts);
        return $lead->fresh();
    }


    public function assignTags(Lead $lead, Collection $tagsToAddOrRemove, array $opts = []): Collection
    {
        $clientId = $lead->client_id;
        $assignType = $opts['assignType'] ?? 'replace';

        $syncMethod = match ($assignType) {
            'replace' => 'sync',
            'remove' => 'detach',
            'add' => 'syncWithoutDetaching',
        };
        $oldTagIds = $lead->tags->pluck('id')->unique();

        $syncedStatus = $lead->tags()->$syncMethod($tagsToAddOrRemove->pluck('id')->unique());
        $lead->saveOrFail();
        $newTags = $lead->fresh('tags')->tags;

        $this->timelineEventsDispatcherService->leadTagsUpdated($lead, $oldTagIds, $newTags, $assignType);
        $this->leadEventsDispatcherService->dispatchSaveLeadTagLastUsedDateJob(
            $clientId, $oldTagIds, $newTags, $assignType
        );
        return $lead->fresh('tags')->tags;
    }


    public function changeStatus(Lead $lead, Status $newStatus, array $opts = []): Status
    {
        // No se puede cambiar de un estado al mismo estado.
        if ($newStatus->id == $lead->status_id) {
            return $newStatus;
        }

        $oldStatus = $lead->status->toArray();
        $lead->status_id = $newStatus->id;
        $lead->last_status_changed_at = new DateTime();
        $lead->saveOrFail();
        $this->timelineEventsDispatcherService->leadStatusUpdated(
            $lead->id, $oldStatus, $lead->fresh('status')->status
        );
        $this->dispatchSendStatusChangeLeadDataToWebhookJobIfEnabled($lead);
        return $newStatus->fresh();
    }


    // Necesita ser llamado en contexto de usuario logueado (usa GetClientFromRequest y GetUserFromRequest)
    public function changeMassiveLeadsStatus(Status $oldStatus, Status $newStatus): array
    {
        $user = $this->getUser();
        $client = $this->getClient();
        $oldStatusArr = $oldStatus->toArray();
        $changedLeadIds = $this->leadRepository->changeMassiveLeadsStatus($oldStatus, $newStatus);

        foreach ($changedLeadIds as $leadId) {
            $this->leadEventsDispatcherService->dispatchLeadStatusMassiveChangedJob(
                $leadId, $newStatus->id, $client->id, $user->id
            );
            $this->timelineEventsDispatcherService->leadStatusUpdated($leadId, $oldStatusArr, $newStatus);
        }
        return $changedLeadIds;
    }


    // Necesita ser llamado en contexto de usuario logueado (usa GetClientFromRequest y GetUserFromRequest)
    public function setMassiveLeadsStatus(Collection $leads, Status $newStatus): Collection
    {
        foreach ($leads as $lead) {
            $lead->oldStatusArr = $leads->first()->status->toArray();
        }

        $user = $this->getUser();
        $client = $this->getClient();
        $changedLeadIds = $this->leadRepository->setMassiveLeadsStatus($leads, $newStatus);

        foreach ($leads as $lead) {
            $this->timelineEventsDispatcherService->leadStatusUpdated($lead->id, $lead->oldStatusArr, $newStatus);
            if ($lead->oldStatusArr['id'] !== $newStatus->id) {
                $this->leadEventsDispatcherService->dispatchLeadStatusMassiveChangedJob(
                    $lead->id, $newStatus->id, $client->id, $user->id
                );

                // Solo llamo webhook si están seteando hasta 60 estados masivos, no más.
                if ($leads->count() <= 60) {
                    $this->dispatchSendStatusChangeLeadDataToWebhookJobIfEnabled($lead);
                }
            }
        }
        return $changedLeadIds;
    }


    public function setMassiveLeadsAcquisitionChannel(
        Collection $leads,
        AcquisitionChannel $newAcquisitionChannel
    ): Collection {
        foreach ($leads as $lead) {
            $lead->oldChannelArr = $lead->acquisitionChannel?->toArray();
        }

        $changedLeadIds = $this->leadRepository->setMassiveLeadsAcquisitionChannel($leads, $newAcquisitionChannel);
        foreach ($leads as $lead) {
            $this->timelineEventsDispatcherService->leadAcquisitionChannelUpdated(
                $lead->id, $lead->oldChannelArr, $newAcquisitionChannel
            );
        }
        return $changedLeadIds;
    }


    public function deleteMassiveLeads(Collection $leads): Collection
    {
        $user = $this->getUser();
        $userIp = $this->getIp();
        $dateNow = new DateTime();
        $client = $this->getClient();
        $deletedLeadIds = new Collection();
        $dateNowTs = $dateNow->getTimestamp();
        $deletedEmailAddrs = new Collection();
        $deletedPhoneNumbers = new Collection();

        DB::beginTransaction();
        try {
            foreach ($leads as $lead) {
                if ($lead->deleted_at) {
                    continue;
                }

                $lead->deleted_at = $dateNow;
                $lead->hash = Lead::buildDeletedHash($lead->hash);
                $lead->saveOrFail();
                
                $lead->tasks()->delete();
                $lead->leadSales()->delete();
                $lead->leadContacts()->delete();
                $lead->proposalsInfo()->delete();

                $deletedPhoneNumbers = $deletedPhoneNumbers->merge($lead->leadContactPhones->pluck('phone'))->unique();
                
                $lead->leadContactPhones()->update(['lead_ids_where_repeated' => null]);
                $lead->leadContactPhones()->delete();
                
                $deletedEmailAddrs = $deletedEmailAddrs->merge($lead->leadContactEmails->pluck('email'))->unique();

                $lead->leadContactEmails()->update(['lead_ids_where_repeated' => null]);
                $lead->leadContactEmails()->delete();
                
                foreach ($lead->googleAPIUserContacts as $googleAPIUserContact) {
                    $googleAPIUserContact->deleted_at = $dateNow;
                    $googleAPIUserContact->deleted_at_ts = $dateNowTs;
                    $googleAPIUserContact->saveOrFail();
                }

                $deletedLeadIds->push($lead->id);
            }
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        DB::commit();

        $this->timelineEventsDispatcherService->leadMassiveDeleted($user, $deletedLeadIds, $userIp);
        $this->clientEventsDispatcherService->dispatchClearClientCacheJob($this->getRequestClientOrNull());
        $this->clientEventsDispatcherService->dispatchSendDeletedLeadsNotificationJob(
            $client, $user, $userIp, $deletedLeadIds->count()
        );
        
        foreach ($deletedEmailAddrs as $deletedEmailAddr) {
            $this->leadEventsDispatcherService->dispatchLeadDuplicatedEmailManagementJob(
                $user->client_id, 'delete', $deletedEmailAddr
            );
        }

        foreach ($deletedPhoneNumbers as $deletedPhoneNumber) {
            $this->leadEventsDispatcherService->dispatchLeadDuplicatedPhoneManagementJob(
                $user->client_id, 'delete', $deletedPhoneNumber
            );
        }

        return $leads;
    }


    public function setLeadTags(Lead $lead, Collection $tags): Lead
    {
        $assignType = 'replace';
        $oldTagIds = $lead->tags->pluck('id');
        $lead = $this->leadRepository->setLeadTags($lead, $tags);
        $newTags = $lead->fresh('tags')->tags;

        $this->timelineEventsDispatcherService->leadTagsUpdated($lead, $oldTagIds, $newTags, $assignType);
        $this->leadEventsDispatcherService->dispatchSaveLeadTagLastUsedDateJob(
            $lead->client_id, $oldTagIds, $newTags, $assignType
        );
        return $lead;
    }


    public function editMassiveLeadsTags(Collection $leads, Collection $tags, array $opts = []): Collection
    {
        $previousDataArr = [];
        foreach ($leads as $lead) {
            $previousDataArr[] = ['lead' => clone $lead, 'leadTagIds' => $lead->tags->pluck('id')];
        }

        $assignType = $opts['assignType'] ?? 'add';
        $changedLeadIds = $this->leadRepository->editMassiveLeadsTags($leads, $tags, ['assignType' => $assignType]);

        foreach ($previousDataArr as $prevData) {
            $prevLead = $prevData['lead'];
            $leadTagIds = $prevData['leadTagIds'];
            $this->timelineEventsDispatcherService->leadTagsUpdated(
                $prevLead, $leadTagIds, $tags, $assignType
            );
            $this->leadEventsDispatcherService->dispatchSaveLeadTagLastUsedDateJob(
                $prevLead->client_id, $leadTagIds, $tags, $assignType
            );
        }
        return $changedLeadIds;
    }


    public function changeMassiveLeadsAcquisitionChannel(
        AcquisitionChannel $originalChannel,
        AcquisitionChannel $newChannel
    ): array {
        $changedLeadIds = $this
            ->leadRepository
            ->changeMassiveLeadsAcquisitionChannel($originalChannel, $newChannel)
        ;
        return $changedLeadIds;
    }


    protected function dispatchSendStatusChangeLeadDataToWebhookJobIfEnabled(Lead $lead): void
    {
        $clientSettings = $lead->client->clientSettings;
        if (!$clientSettings->enable_integration_api) {
            return;
        }

        $integrationAPIDispatcher = $this->integrationAPIEventsDispatcherService;
        $webhookUrl = $clientSettings->lead_status_change_trigger_webhook;
        if ($webhookUrl) {
            $integrationAPIDispatcher->dispatchSendStatusChangeLeadDataToWebhookJob($lead, $webhookUrl);
        }

        $zapierWebhookUrl = $clientSettings->lead_status_change_trigger_zapier_webhook;
        if ($zapierWebhookUrl) {
            $integrationAPIDispatcher->dispatchSendStatusChangeLeadDataToWebhookJob($lead, $zapierWebhookUrl);
        }
        
        $makeWebhookUrl = $clientSettings->lead_status_change_trigger_make_webhook;
        if ($makeWebhookUrl) {
            $integrationAPIDispatcher->dispatchSendStatusChangeLeadDataToWebhookJob($lead, $makeWebhookUrl);
        }
    }

}
