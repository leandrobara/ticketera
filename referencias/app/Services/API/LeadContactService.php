<?php

namespace App\Services\API;

use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\LeadContact;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Repositories\LeadContactRepository;
use App\Services\Traits\HandleDBTransactions;
use App\Services\API\LeadContactEmailService;
use App\Services\API\LeadContactPhoneService;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\Dispatchers\SearchLeadEventsDispatcherService;
use App\Services\API\Dispatchers\GoogleContactsEventsDispatcherService;
use App\Exceptions\Services\LeadContactService\DeleteMainLeadContactException;


class LeadContactService
{

    use GetClientFromRequest, HandleDBTransactions;

    private $leadContactRepository;
    private $leadContactEmailService;
    private $leadContactPhoneService;
    private $searchLeadEventsDispatcherService;
    private $googleContactsEventsDispatcherService;


    public function __construct(
        LeadContactRepository $leadContactRepository,
        LeadContactEmailService $leadContactEmailService,
        LeadContactPhoneService $leadContactPhoneService,
        SearchLeadEventsDispatcherService $searchLeadEventsDispatcherService,
        GoogleContactsEventsDispatcherService $googleContactsEventsDispatcherService
    ) {
        $this->leadContactRepository = $leadContactRepository;
        $this->leadContactEmailService = $leadContactEmailService;
        $this->leadContactPhoneService = $leadContactPhoneService;
        $this->searchLeadEventsDispatcherService = $searchLeadEventsDispatcherService;
        $this->googleContactsEventsDispatcherService = $googleContactsEventsDispatcherService;
    }


    public function find(int $id)
    {
        return LeadContact::findOrFail($id);
    }


    public function create(Lead $lead, array $data, array $opts = [])
    {
        $leadContacts = $lead->leadContacts;
        $isNewLead = $opts['isNewLead'] ?? false;
        $useTransaction = $opts['useTransaction'] ?? true;
        $lastOrder = $data['order'] ?? $leadContacts->max('order');

        $data['lead_id'] = $lead->id;
        $data['order'] = $lastOrder === null ? 0 : $lastOrder + 1;
        $data['is_main'] = $this->getLeadIsMain($lead, $data);
        $data['client_id'] = $data['client_id'] ?? $this->getClient()->id;

        // get email & phone and remove from data to create lead contact
        $email = Arr::pull($data, 'email');
        $email2 = Arr::pull($data, 'email2');
        $phone = Arr::pull($data, 'phone');
        $phone2 = Arr::pull($data, 'phone2');

        try {
            $this->beginTransaction($useTransaction);

            $leadContact = $this->leadContactRepository->create($data);

            if ($email) {
                $this->leadContactEmailService->create(
                    $leadContact,
                    ['email' => $email, 'client_id' => $data['client_id']],
                    ['isNewLead' => $isNewLead]
                );
            }
            if ($phone) {
                $this->leadContactPhoneService->create(
                    $leadContact,
                    ['phone' => $phone, 'client_id' => $data['client_id']],
                    ['isNewLead' => $isNewLead]
                );
            }
            if ($email2) {
                $this->leadContactEmailService->create(
                    $leadContact,
                    ['email' => $email2, 'client_id' => $data['client_id']],
                    ['isNewLead' => $isNewLead]
                );
            }
            if ($phone2) {
                $this->leadContactPhoneService->create(
                    $leadContact,
                    ['phone' => $phone2, 'client_id' => $data['client_id']],
                    ['isNewLead' => $isNewLead]
                );
            }

            $this->commitTransaction($useTransaction);
        } catch (Throwable $e) {
            $this->rollBackTransaction($useTransaction);
            throw $e;
        }

        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);

        if (!$isNewLead) {
            $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
        }

        return $leadContact->fresh();
    }


    public function update(LeadContact $leadContact, $data)
    {
        $lead = $leadContact->lead;
        $leadContact = $this->leadContactRepository->update($leadContact, $data);
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
        return $leadContact;
    }


    public function delete(LeadContact $leadContact)
    {
        $lead = $leadContact->lead;
        $leadContacts = $lead->leadContacts;

        if ($leadContacts->count() <= 1) {
            throw new DeleteMainLeadContactException();
        }

        $otherContacts = $leadContacts->filter(function ($contact) use ($leadContact) {
            return $contact->id !== $leadContact->id;
        });
    
        try {
            DB::beginTransaction();

            $isMain = $leadContact->is_main;
            $this->deleteDependencies($leadContact);
            $leadContact->delete();
            if ($isMain) {
                $otherContacts->first()->is_main = true;
                $otherContacts->first()->saveOrFail();
            }
            $this->reOrder($otherContacts);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    
        $this->searchLeadEventsDispatcherService->dispatchUpdateLeadSearchInfoJob($lead->id);
        $this->googleContactsEventsDispatcherService->dispatchSyncUpdatedLeadWithGoogleContactsJob($lead);
        
        return $leadContact->fresh();
    }


    private function getLeadIsMain(Lead $lead, array $data)
    {
        $mainContact = $lead->mainLeadContact;
        if (!$mainContact) {
            return true;
        }

        $isMain = $data['is_main'] ?? false;
        if ($isMain) {
            $mainContact->is_main = false;
            $mainContact->saveOrFail();
            return true;
        }
        return $isMain;
    }


    private function reOrder($leadContacts)
    {
        $order = 0;
        foreach ($leadContacts as $leadContact) {
            $leadContact->order = $order;
            $leadContact->saveOrFail();
            $order++;
        }
    }


    private function deleteDependencies($leadContact)
    {
        $leadContact->leadContactEmails->each(function ($leadContactEmails) {
            $leadContactEmails->delete();
        });

        $leadContact->leadContactPhones->each(function ($leadContactPhones) {
            $leadContactPhones->delete();
        });
    }

}
