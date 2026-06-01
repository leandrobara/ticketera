<?php

namespace App\Services\API;

use DateTime;
use Exception;
use Throwable;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadContactPhone;
use App\Services\Traits\Sortable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\LeadContactPhoneRepository;
use App\Services\API\Dispatchers\LeadEventsDispatcherService;
use App\Services\API\Dispatchers\SearchLeadEventsDispatcherService;
use App\Services\API\Dispatchers\GoogleContactsEventsDispatcherService;


class LeadContactPhoneService
{

    use GetClientFromRequest, Sortable;


    public function __construct(
        protected LeadContactPhoneRepository $leadContactPhoneRepository,
        protected LeadEventsDispatcherService $leadEventsDispatcherService,
        protected SearchLeadEventsDispatcherService $searchLeadEventsDispatcherService,
        protected GoogleContactsEventsDispatcherService $googleContactsEventsDispatcherService
    ) {
    }


    public function create(LeadContact $leadContact, array $data, array $opts = []): LeadContactPhone
    {
        $clientId = $leadContact->client_id;
        $leadContactPhone = $this->findOneWithTrashedByLeadAndPhone($leadContact->lead, $data['phone']);
        $isNewLead = $opts['isNewLead'] ?? false;
        $lastOrder = $leadContact->leadContactPhones()->max('order');

        // if phone exists and is deleted
        if ($leadContactPhone && $leadContactPhone->deleted_at) {
            $leadContactPhone->fill($data);
            $leadContactPhone->deleted_at = null;
            $leadContactPhone->lead_ids_where_repeated = null;
            if (!isset($data['lead_contact_id'])) {
                $leadContactPhone->lead_contact_id = $leadContact->id;
            }
            if (!isset($data['order'])) {
                $leadContactPhone->order = $lastOrder === null ? 0 : $lastOrder + 1;
            }
            $leadContactPhone->normalized_phone = $leadContactPhone->getWhatsAppFormattedPhone(
                $leadContact->client->country_code, $leadContact->client->clientSettings
            );
            $leadContactPhone->normalized_hash = LeadContactPhone::buildNormalizedHash(
                $leadContactPhone->normalized_phone
            );

            $leadContactPhone->saveOrFail();
            $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($leadContact->lead_id);

            return $leadContactPhone;
        }

        $lead = $leadContact->lead;
        $data['lead_id'] = $lead->id;
        $data['order'] = $lastOrder === null ? 0 : $lastOrder + 1;
        $data['lead_contact_id'] = $leadContact->id;
        $data['client_id'] = $data['client_id'] ?? $this->getClient()->id;
        $newLeadContactPhone = $this->leadContactPhoneRepository->create($data, $leadContact);
    
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
        if (!$isNewLead) {
            $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
        }

        $this->leadEventsDispatcherService->dispatchLeadDuplicatedPhoneManagementJob(
            $clientId, 'create', $newLeadContactPhone->phone
        );

        return $newLeadContactPhone;
    }


    public function update(LeadContactPhone $leadContactPhone, array $data): LeadContactPhone
    {
        $lead = $leadContactPhone->lead;
        $clientId = $leadContactPhone->client_id;
        $newPhoneNumber = $data['phone'] ?? null;
        $oldPhoneNumber = $leadContactPhone->phone;
        $existentLeadContactPhone = $this->findOneWithTrashedByLeadAndPhone($lead, $data['phone']);
        $isDifferent = $existentLeadContactPhone && ($existentLeadContactPhone->id != $leadContactPhone->id);

        // if phone exists and it is deleted.
        if ($isDifferent && $existentLeadContactPhone->deleted_at) {
            $existentLeadContactPhone->fill($data);
            $existentLeadContactPhone->deleted_at = null;
            $existentLeadContactPhone->lead_ids_where_repeated = null;
            $existentLeadContactPhone->order = $leadContactPhone->order;
            $existentLeadContactPhone->lead_contact_id = $leadContactPhone->lead_contact_id;
            $existentLeadContactPhone->normalized_phone = $existentLeadContactPhone->getWhatsAppFormattedPhone(
                $existentLeadContactPhone->client->country_code, $existentLeadContactPhone->client->clientSettings
            );
            $existentLeadContactPhone->normalized_hash = LeadContactPhone::buildNormalizedHash(
                $existentLeadContactPhone->normalized_phone
            );
            $existentLeadContactPhone->saveOrFail();
            
            $leadContactPhone->deleted_at = new DateTime();
            $leadContactPhone->saveOrFail();

            $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
            $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
            $this->leadEventsDispatcherService->dispatchLeadDuplicatedPhoneManagementJob(
                $clientId, 'update', $newPhoneNumber, $oldPhoneNumber
            );
            return $existentLeadContactPhone;
        }

        $leadContactPhone = $this->leadContactPhoneRepository->update($leadContactPhone, $data);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
        $this->leadEventsDispatcherService->dispatchLeadDuplicatedPhoneManagementJob(
            $clientId, 'update', $newPhoneNumber, $oldPhoneNumber
        );
        return $leadContactPhone;
    }


    public function delete(LeadContactPhone $leadContactPhone)
    {
        $lead = $leadContactPhone->lead;
        $phoneNumber = $leadContactPhone->phone;
        $clientId = $leadContactPhone->client_id;
        $leadContact = $leadContactPhone->leadContact;
        $leadContactPhones = $leadContact->leadContactPhones;

        $otherContactsPhones = $leadContactPhones->filter(function ($phones) use ($leadContactPhone) {
            return $phones->id !== $leadContactPhone->id;
        });

        try {
            DB::beginTransaction();
            $deleted = $this->leadContactPhoneRepository->delete($leadContactPhone);
            $this->reOrder($otherContactsPhones);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
        $this->leadEventsDispatcherService->dispatchLeadDuplicatedPhoneManagementJob($clientId, 'delete', $phoneNumber);

        return $deleted;
    }


    public function updateAndSetRepeteadLeadIdsField(Client $client, string $phoneNumber, array $opts = []): ?Collection
    {
        $skipUpdateIfSingleResult = $opts['skipUpdateIfSingleResult'] ?? false;
        if (!$phoneNumber) {
            return new Collection();
        }

        $fields = ['id', 'lead_id'];
        $leadContactPhones = $this
            ->leadContactPhoneRepository
            ->findRawByClientAndPhone($client, $phoneNumber, $fields)
            ->take(100) // tomo como máximo 100 duplicados
        ;
        if ($leadContactPhones->count() == 0) {
            return new Collection();
        }
        if ($leadContactPhones->count() == 1 && $skipUpdateIfSingleResult) {
            return new Collection();
        }
        
        $leadIdsWhereRepetead = $leadContactPhones->pluck('lead_id');
        if ($leadIdsWhereRepetead->count() == 1) {
            $leadIdsWhereRepetead = null;
        }

        $fieldsToUpdate['lead_ids_where_repeated'] = $leadIdsWhereRepetead;
        $updatedCount = $this->leadContactPhoneRepository->updateMultiple($leadContactPhones, $fieldsToUpdate);
        
        $leadContactPhones = $leadContactPhones->map(function ($e) use ($leadIdsWhereRepetead) {
            $e->lead_ids_where_repeated = $leadIdsWhereRepetead;
            return $e;
        });
        return $leadContactPhones;
    }


    public function findByClientAndLeadIds(Client $client, Collection $leadIds): Collection
    {
        return $this->leadContactPhoneRepository->findByClientAndLeadIds($client, $leadIds);
    }


    public function findByClientAndIds(Client $client, Collection $leadContactPhoneIds, array $opts = []): Collection
    {
        return $this->leadContactPhoneRepository->findByClientAndIds($client, $leadContactPhoneIds, $opts);
    }


    public function findOneWithTrashedByLeadAndPhone(Lead $lead, string $phone): ?LeadContactPhone
    {
        $leadContactPhone = $this->leadContactPhoneRepository->findOneWithTrashedByLeadAndPhone($lead, $phone);
        return $leadContactPhone;
    }


    public function findTrashedByLead(Lead $lead): ?Collection
    {
        $leadContactPhoneTrashed = $this->leadContactPhoneRepository->findTrashedByLead($lead);
        return $leadContactPhoneTrashed;
    }


    public function countRepeatedPhonesInOtherLeads(Lead $lead): int
    {
        return $this->leadContactPhoneRepository->countRepeatedPhonesInOtherLeads($lead);
    }


    public function findNormalizedPhonesByLeadFilters(Client $client, array $leadFilters): array
    {
        return $this->leadContactPhoneRepository->findNormalizedPhonesByLeadFilters($client, $leadFilters);
    }


    public function normalizedLeadContactPhoneExists(Client $client, string $normalizedPhone): bool
    {
        return $this->leadContactPhoneRepository->normalizedLeadContactPhoneExists($client, $normalizedPhone);
    }


    public function findNormalizedPhonesByLeadId(Client $client, int $leadId): array
    {
        return $this->leadContactPhoneRepository->findNormalizedPhonesByLeadId($client, $leadId);
    }

}
