<?php

namespace App\Services\API;

use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadContact;
use App\Models\LeadContactEmail;
use App\Services\Traits\Sortable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\LeadContactEmailRepository;
use App\Services\API\Dispatchers\LeadEventsDispatcherService;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Services\API\Dispatchers\SearchLeadEventsDispatcherService;
use App\Services\API\Dispatchers\GoogleContactsEventsDispatcherService;
use App\Services\API\Dispatchers\EmailValidationEventsDispatcherService;


class LeadContactEmailService
{

    use Sortable;
    use GetClientFromRequest;

    private $leadContactEmailRepository;
    private $leadEventsDispatcherService;
    private $clientEventsDispatcherService;
    private $searchLeadEventsDispatcherService;
    private $googleContactsEventsDispatcherService;
    private $emailValidationEventsDispatcherService;

    public function __construct(
        LeadContactEmailRepository $leadContactEmailRepository,
        LeadEventsDispatcherService $leadEventsDispatcherService,
        SearchLeadEventsDispatcherService $searchLeadEventsDispatcherService,
        EmailValidationEventsDispatcherService $emailValidationEventsDispatcherService,
        GoogleContactsEventsDispatcherService $googleContactsEventsDispatcherService,
        ClientEventsDispatcherService $clientEventsDispatcherService,
    ) {
        $this->leadContactEmailRepository = $leadContactEmailRepository;
        $this->leadEventsDispatcherService = $leadEventsDispatcherService;
        $this->clientEventsDispatcherService = $clientEventsDispatcherService;
        $this->searchLeadEventsDispatcherService = $searchLeadEventsDispatcherService;
        $this->googleContactsEventsDispatcherService = $googleContactsEventsDispatcherService;
        $this->emailValidationEventsDispatcherService = $emailValidationEventsDispatcherService;
    }


    public function findFirstOneByClientAndEmail(Client $client, string $email): ?LeadContactEmail
    {
        return $this->leadContactEmailRepository->findFirstOneByClientAndEmail($client, $email);
    }


    public function findOtherFromSameClient(LeadContactEmail $leadContactEmail): ?LeadContactEmail
    {
        return $this->leadContactEmailRepository->findOtherFromSameClient($leadContactEmail);
    }


    public function findByClientAndEmail(Client $client, string $email, array $fields = []): Collection
    {
        return $this->leadContactEmailRepository->findByClientAndEmail($client, $email, $fields);
    }


    public function findByClientAndLeadIds(Client $client, Collection $leadIds): Collection
    {
        return $this->leadContactEmailRepository->findByClientAndLeadIds($client, $leadIds);
    }


    public function findByClientAndIds(Client $client, Collection $leadContactEmailIds, array $opts = []): Collection
    {
        return $this->leadContactEmailRepository->findByClientAndIds($client, $leadContactEmailIds, $opts);
    }

    
    public function markAsBounced(Collection $leadContactEmails): bool
    {
        foreach ($leadContactEmails as $leadContactEmail) {
            $this->leadContactEmailRepository->update($leadContactEmail, ['bounced' => true]);
        }
        return true;
    }


    public function markAsComplained(Collection $leadContactEmails): bool
    {
        foreach ($leadContactEmails as $leadContactEmail) {
            $this->leadContactEmailRepository->update($leadContactEmail, ['complained' => true]);
        }
        return true;
    }


    public function markAsUnsubscribed(Collection $leadContactEmails): bool
    {
        foreach ($leadContactEmails as $leadContactEmail) {
            $this->leadContactEmailRepository->update($leadContactEmail, ['unsubscribed' => true]);
        }
        return true;
    }


    public function create(LeadContact $leadContact, array $data, array $opts = []): LeadContactEmail
    {
        $lead = $leadContact->lead;
        $clientId = $leadContact->client_id;
        $isNewLead = $opts['isNewLead'] ?? false;
        $lastOrder = $leadContact->leadContactEmails()->max('order');
        $deletedLeadContactEmail = $this->findOneWithTrashedByLeadAndEmail($lead, $data['email']);
        
        // if email exists and is not deleted
        if ($deletedLeadContactEmail && $deletedLeadContactEmail->deleted_at) {
            $deletedLeadContactEmail->deleted_at = null;
            $deletedLeadContactEmail->lead_ids_where_repeated = null;
            $deletedLeadContactEmail->fill($data);
            if (!isset($data['lead_contact_id'])) {
                $deletedLeadContactEmail->lead_contact_id = $leadContact->id;
            }
            if (!isset($data['order'])) {
                $deletedLeadContactEmail->order = $lastOrder === null ? 0 : $lastOrder + 1;
            }
            $deletedLeadContactEmail->saveOrFail();

            $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
            $this->leadEventsDispatcherService->dispatchLeadDuplicatedEmailManagementJob(
                $clientId, 'create', $deletedLeadContactEmail->email
            );
            return $deletedLeadContactEmail;
        }

        $data['lead_id'] = $lead->id;
        $data['client_id'] = $clientId;
        $data['lead_contact_id'] = $leadContact->id;
        $data['order'] = $lastOrder === null ? 0 : $lastOrder + 1;

        $newLeadContactEmail = $this->leadContactEmailRepository->create($data);
        $newLeadContactEmail = $this->fillEmailNotificationsFlags($newLeadContactEmail);
        
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
        $this->emailValidationEventsDispatcherService->dispatchValidateLeadContactEmailJob($newLeadContactEmail, 5);
        $this->leadEventsDispatcherService->dispatchLeadDuplicatedEmailManagementJob(
            $clientId, 'create', $newLeadContactEmail->email
        );

        if (!$isNewLead) {
            $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
        }

        return $newLeadContactEmail;
    }


    public function update(LeadContactEmail $leadContactEmail, array $data): LeadContactEmail
    {
        $deletedLeadContactEmail = null;
        $lead = $leadContactEmail->lead;
        $newEmailAddr = $data['email'] ?? null;
        $clientId = $leadContactEmail->client_id;
        $oldEmailAddr = $leadContactEmail->email;
        $isUpdatingEmailAddr = $newEmailAddr && ($newEmailAddr != $oldEmailAddr);

        if ($isUpdatingEmailAddr) {
            $deletedLeadContactEmail = $this->findOneWithTrashedByLeadAndEmail($lead, $newEmailAddr);
        }

        if ($deletedLeadContactEmail && $deletedLeadContactEmail->deleted_at) {
            $deletedLeadContactEmail->deleted_at = null;
            $deletedLeadContactEmail->lead_ids_where_repeated = null;
            $deletedLeadContactEmail->lead_contact_id = $leadContactEmail->lead_contact_id;
            try {
                DB::beginTransaction();
                $deletedLeadContactEmail->saveOrFail();
                $this->leadContactEmailRepository->delete($leadContactEmail);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
            $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
            $this->leadEventsDispatcherService->dispatchLeadDuplicatedEmailManagementJob(
                $clientId, 'update', $newEmailAddr, $oldEmailAddr
            );
            return $deletedLeadContactEmail;
        }

        $updatedLeadContactEmail = $this->leadContactEmailRepository->update($leadContactEmail, $data);
        $updatedLeadContactEmail = $this->fillEmailNotificationsFlags($updatedLeadContactEmail);
        
        if ($isUpdatingEmailAddr) {
            $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
            $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
            $this->emailValidationEventsDispatcherService->dispatchValidateLeadContactEmailJob(
                $updatedLeadContactEmail, 5
            );
            $this->leadEventsDispatcherService->dispatchLeadDuplicatedEmailManagementJob(
                $clientId, 'update', $newEmailAddr, $oldEmailAddr
            );
        }

        return $updatedLeadContactEmail;
    }


    // $emailsData: Collection de ['email' => string, 'isValid' => bool, 'isSubscribed' => bool]
    public function updateMultipleValidAndSubscribedStatus(Client $client, Collection $emailsData): int
    {
        // Normaliza input y asegura estructura
        $emailsData = $emailsData->map(function ($row) {
            return [
                'isValid' => (bool) ($row['isValid'] ?? false),
                'email' => trim(strtolower($row['email'] ?? '')),
                'isSubscribed' => (bool) ($row['isSubscribed'] ?? false),
            ];
        })->filter(function ($row) {
            return !empty($row['email']);
        });

        $updatedCount = $this->leadContactEmailRepository->updateMultipleValidAndSubscribedStatus(
            $client, $emailsData
        );
        $this->clientEventsDispatcherService->dispatchClearClientCacheJob($client);
        return $updatedCount;
    }


    public function fillEmailNotificationsFlags(LeadContactEmail $leadContactEmail): LeadContactEmail
    {
        $existentEmail = $this->findOtherFromSameClient($leadContactEmail);
        if ($existentEmail) {
            $leadContactEmail->bounced = $existentEmail->bounced;
            $leadContactEmail->is_valid = $existentEmail->is_valid;
            $leadContactEmail->complained = $existentEmail->complained;
            $leadContactEmail->validations = $existentEmail->validations;
            $leadContactEmail->unsubscribed = $existentEmail->unsubscribed;
        } else {
            $leadContactEmail->bounced = false;
            $leadContactEmail->is_valid = true;
            $leadContactEmail->complained = false;
            $leadContactEmail->validations = null;
            $leadContactEmail->unsubscribed = false;
        }
        $leadContactEmail->saveOrFail();
        $leadContactEmail = $leadContactEmail->fresh();
        return $leadContactEmail;
    }


    public function delete(LeadContactEmail $leadContactEmail)
    {
        $leadContact = $leadContactEmail->leadContact;
        $leadContactEmails = $leadContact->leadContactEmails;
        
        $otherContactsEmails = $leadContactEmails->filter(
            function ($emails) use ($leadContactEmail) {
                return $emails->id !== $leadContactEmail->id;
            }
        );

        try {
            DB::beginTransaction();
            $deleted = $this->leadContactEmailRepository->delete($leadContactEmail);
            $this->reOrder($otherContactsEmails);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $lead = $leadContactEmail->lead;
        $emailAddr = $leadContactEmail->email;
        $clientId = $leadContactEmail->client_id;
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
        $this->leadEventsDispatcherService->dispatchLeadDuplicatedEmailManagementJob($clientId, 'delete', $emailAddr);
        return $deleted;
    }


    public function findOneWithTrashedByLeadAndEmail(Lead $lead, string $emailAddr): ?LeadContactEmail
    {
        return $this->leadContactEmailRepository->findOneWithTrashedByLeadAndEmail($lead, $emailAddr);
    }


    public function countRepeatedEmailsInOtherLeads(Lead $lead): int
    {
        return $this->leadContactEmailRepository->countRepeatedEmailsInOtherLeads($lead);
    }
    

    public function updateAndSetRepeteadLeadIdsField(Client $client, string $emailAddr, array $opts = []): ?Collection
    {
        $skipUpdateIfSingleResult = $opts['skipUpdateIfSingleResult'] ?? false;
        if (!$emailAddr) {
            return new Collection();
        }

        $fields = ['id', 'lead_id'];
        $leadContactEmails = $this
            ->leadContactEmailRepository
            ->findRawByClientAndEmail($client, $emailAddr, $fields)
            ->take(100) // tomo como máximo 100 duplicados
        ;
        if ($leadContactEmails->count() == 0) {
            return new Collection();
        }
        if ($leadContactEmails->count() == 1 && $skipUpdateIfSingleResult) {
            return new Collection();
        }
        
        $leadIdsWhereRepetead = $leadContactEmails->pluck('lead_id');
        if ($leadIdsWhereRepetead->count() == 1) {
            $leadIdsWhereRepetead = null;
        }

        $fieldsToUpdate['lead_ids_where_repeated'] = $leadIdsWhereRepetead;
        $updatedCount = $this->leadContactEmailRepository->updateMultiple($leadContactEmails, $fieldsToUpdate);
        
        $leadContactEmails = $leadContactEmails->map(function ($e) use ($leadIdsWhereRepetead) {
            $e->lead_ids_where_repeated = $leadIdsWhereRepetead;
            return $e;
        });
        return $leadContactEmails;
    }


    public function findTrashedByLead(Lead $lead): ?Collection
    {
        $leadContactEmailTrashed = $this->leadContactEmailRepository->findTrashedByLead($lead);
        return $leadContactEmailTrashed;
    }

}
