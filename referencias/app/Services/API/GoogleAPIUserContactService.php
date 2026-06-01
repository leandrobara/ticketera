<?php

namespace App\Services\API;

use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\GoogleAPIUserContact;
use App\DTO\GoogleAPI\GoogleAPIContactDTO;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\GoogleAPIUserContactRepository;
use App\Services\API\GoogleAPI\GooglePeopleAPIService;
use App\Exceptions\Services\GoogleAPI\GoogleAPIIsNotEnabled;


class GoogleAPIUserContactService
{

    use GetClientFromRequest;

    private $googlePeopleAPIService;
    private $googleAPIUserContactRepository;


    public function __construct(
        GoogleAPIUserContactRepository $googleAPIUserContactRepository,
        GooglePeopleAPIService $googlePeopleAPIService
    ) {
        $this->googlePeopleAPIService = $googlePeopleAPIService;
        $this->googleAPIUserContactRepository = $googleAPIUserContactRepository;
    }

    
    public function findAndPopulate(int $id): ?GoogleAPIUserContact
    {
        $model = $this->googleAPIUserContactRepository->find($id);
        if ($model) {
            $this->populateModelFromGooglePeople($model);
        }
        return $model;
    }


    public function find(int $id): ?GoogleAPIUserContact
    {
        return $this->googleAPIUserContactRepository->find($id);
    }


    // Llena con la info traida de la API (objeto GoogleAPIContactDTO dentro del modelo en $googleAPIContactDTO)
    public function populateModelFromGooglePeople(GoogleAPIUserContact $model): GoogleAPIUserContact
    {
        $dto = $this->googlePeopleAPIService->findContactByUserContactModel($model);
        $model->googleAPIContactDTO = $dto;
        return $model;
    }


    public function findAllByClient(?Client $client): Collection
    {
        $client = $client ?? $this->getClient();
        return $this->googleAPIUserContactRepository->findAllByClient($client);
    }


    public function syncLead(Lead $lead, User $user): ?GoogleAPIUserContact
    {
        if (!$user->googlePeopleAPIUserToken) {
            return null;
        }
        $googleContactsAPIEnabled = $this->googlePeopleAPIService->isAPIEnabled($user->googlePeopleAPIUserToken);
        if (!$googleContactsAPIEnabled) {
            $msg = "syncLead(leadId {$lead->id}, userId: {$user->id}) - Google API is not enabled";
            throw new GoogleAPIIsNotEnabled($msg);
        }

        $existentContact = $lead->getGoogleAPIUserContact($user);
        if (!$existentContact) {
            $syncedContact = $this->createAndSaveAtGooglePeopleByLeadAndUser($lead, $user);
        } else {
            $syncedContact = $this->updateAtGooglePeople($existentContact);
        }
        return $syncedContact;
    }


    public function unsyncLead(Lead $lead, User $user): ?GoogleAPIUserContact
    {
        if (!$user->googlePeopleAPIUserToken) {
            return null;
        }
        $googleContactsAPIEnabled = $this->googlePeopleAPIService->isAPIEnabled($user->googlePeopleAPIUserToken);
        if (!$googleContactsAPIEnabled) {
            return null;
        }
        $existentContact = $lead->getGoogleAPIUserContact($user);
        if (!$existentContact) {
            return null;
        }

        try {
            DB::beginTransaction();

            $deletedContact = $this->delete($existentContact);
            $this->deleteAtGooglePeople($existentContact);
            
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $deletedContact;
    }


    public function createAndSaveAtGooglePeopleByLeadAndUser(Lead $lead, User $user): GoogleAPIUserContact
    {
        try {
            // Evito transaction por que esto puede llegar a tener mucho timeout, hago un firulete en su lugar.
            // DB::beginTransaction();
            $dto = $this->googlePeopleAPIService->createNewContactAtGoogle($lead, $user);
            $data = [
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'client_id' => $user->client->id,
                'resource_name' => $dto->resourceName,
            ];
            $model = $this->create($data);
            $model->googleAPIContactDTO = $dto;
            // DB::commit();
        } catch (Throwable $e) {
            // DB::rollBack();
            throw $e;
        }

        return $model;
    }


    public function create($data): GoogleAPIUserContact
    {
        if (!$data['client_id'] ?? null) {
            $client = $data['client'] ?? $this->getClient();
            $data['client_id'] = $client->id;
            unset($data['client']);
        }

        $model = $this->googleAPIUserContactRepository->create($data);
        return $model;
    }


    public function update(GoogleAPIUserContact $model, array $data): GoogleAPIUserContact
    {
        return $this->googleAPIUserContactRepository->update($model, $data);
    }


    public function updateAtGooglePeople(GoogleAPIUserContact $model): GoogleAPIUserContact
    {
        $dto = $this->googlePeopleAPIService->updateContactFromUserContactModel($model);
        $model->googleAPIContactDTO = $dto;
        return $model;
    }


    public function delete(GoogleAPIUserContact $model): GoogleAPIUserContact
    {
        return $this->googleAPIUserContactRepository->delete($model);
    }


    public function deleteAtGooglePeople(GoogleAPIUserContact $model): bool
    {
        return $this->googlePeopleAPIService->deleteContact($model);
    }

}