<?php

namespace App\Services\API\GoogleAPI;

use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use App\Helpers\GoogleAPIHelper;
use Google\Service\PeopleService;
use App\Models\GoogleAPIUserToken;
use Illuminate\Support\Collection;
use App\Models\GoogleAPIUserContact;
use Google\Service\PeopleService\Person;
use App\DTO\GoogleAPI\GoogleAPIContactDTO;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\GoogleAPI\GoogleCommonAPIService;
use App\Exceptions\Services\GoogleAPI\UserGoogleAPITokenNotFoundException;
use App\Exceptions\Services\GoogleAPI\MissingClientyIdInContactDTOException;


class GooglePeopleAPIService
{

    use GetClientFromRequest;

    private $googleCommonAPIService;

    const PEOPLE_SCOPE = GoogleAPIHelper::PEOPLE_SCOPE;


    public function __construct(
        GoogleCommonAPIService $googleCommonAPIService
    ) {
        $this->googleCommonAPIService = $googleCommonAPIService;
    }


    public function getGoogleAuthUrl(User $user): string
    {
        $url = $this->googleCommonAPIService->getGoogleAuthUrl($user, [self::PEOPLE_SCOPE]);
        return $url;
    }


    public function getAndStoreAccessTokenFromAuthCode(
        User $user,
        string $authCode
    ): GoogleAPIUserToken {
        $tokenType = GoogleAPIUserToken::PEOPLE_API_TYPE;
        return $this->googleCommonAPIService->getAndStoreAccessTokenFromAuthCode($user, $tokenType, $authCode);
    }


    public function isAPIEnabled(?GoogleAPIUserToken $googleAPIUserToken): bool
    {
        if (!$googleAPIUserToken) {
            return false;
        }
        return $this->googleCommonAPIService->isUserGoogleAPITokenEnabled($googleAPIUserToken, self::PEOPLE_SCOPE);
    }


    public function findAllContactsByUser(User $user): Collection
    {
        $peopleService = $this->getPeopleService($user->googlePeopleAPIUserToken);
        $personFields = 'names,emailAddresses,organizations,phoneNumbers,clientData,externalIds,metadata,userDefined';
        $optParams = ['pageSize' => 1000, 'personFields' => $personFields];
        
        $contacts = new Collection();
        do {
            $apiResults = $peopleService->people_connections->listPeopleConnections('people/me', $optParams);
            $nextPageToken = $apiResults->nextPageToken;

            foreach ($apiResults->getConnections() as $i => $person) {
                $contact = GoogleAPIContactDTO::buildFromGoogleAPIPerson($person);
                $contacts->push($contact);
            }
        } while ($nextPageToken);
        return $contacts;
    }


    public function findPersonByUserContactModel(GoogleAPIUserContact $userContactModel): ?Person
    {
        $peopleService = $this->getPeopleService($userContactModel->user->googlePeopleAPIUserToken);
        $personFields = 'names,emailAddresses,organizations,phoneNumbers,clientData,externalIds,metadata,userDefined';
        $optParams = ['personFields' => $personFields];
        $contacts = new Collection();
        try {
            $person = $peopleService->people->get($userContactModel->resource_name, $optParams);
            return $person;
        } catch (Exception $e) {
            if ($e->getCode() != 404) {
                throw $e;
            }
        }
        return null;
    }


    public function findContactByUserContactModel(GoogleAPIUserContact $model): ?GoogleAPIContactDTO
    {
        $person = $this->findPersonByUserContactModel($model);
        if (!$person) {
            return null;
        }
        $dto = GoogleAPIContactDTO::buildFromGoogleAPIPerson($person);
        return $dto;
    }


    public function createNewContactAtGoogle(Lead $lead, User $user): GoogleAPIContactDTO
    {
        $dto = GoogleAPIContactDTO::buildFromLead($lead);
        $dto = $this->createNewContact($user, $dto);
        return $dto;
    }


    public function createNewContactFromUserContactModel(GoogleAPIUserContact $model): GoogleAPIContactDTO
    {
        $dto = GoogleAPIContactDTO::buildFromLead($model->lead);
        $dto = $this->createNewContact($model->user, $dto);
        return $dto;
    }


    public function createNewContact(User $user, GoogleAPIContactDTO $dto): GoogleAPIContactDTO
    {
        $peopleService = $this->getPeopleService($user->googlePeopleAPIUserToken);
        $person = $this->buildNewGoogleAPIPersonFromDTO($dto);
        $newPerson = $peopleService->people->createContact($person);
        $newDto = GoogleAPIContactDTO::buildFromGoogleAPIPerson($newPerson);
        return $newDto;
    }


    public function updateContactFromUserContactModel(GoogleAPIUserContact $model): GoogleAPIContactDTO
    {
        $existentPerson = $this->findPersonByUserContactModel($model);
        $dtoToSave = GoogleAPIContactDTO::buildFromUserContactModel($model);
        $personToSave = $this->buildNewGoogleAPIPersonFromDTO($dtoToSave);
        $personToSave->etag = $existentPerson->etag;
        $dto = $this->updateContact($model->user, $model->resource_name, $personToSave);
        return $dto;
    }


    public function updateContact(User $user, string $resourceName, Person $personToUpdate): GoogleAPIContactDTO
    {
        $service = $this->getPeopleService($user->googlePeopleAPIUserToken);
        $personFields = 'names,emailAddresses,organizations,phoneNumbers,clientData,externalIds,userDefined';
        $optParams = ['updatePersonFields' => $personFields];
        $updatedPerson = $service->people->updateContact($resourceName, $personToUpdate, $optParams);
        $updatedDto = GoogleAPIContactDTO::buildFromGoogleAPIPerson($updatedPerson);
        return $updatedDto;
    }

    
    public function deleteContact(GoogleAPIUserContact $model): bool
    {
        $service = $this->getPeopleService($model->user->googlePeopleAPIUserToken);
        $service->people->deleteContact($model->resource_name);
        return true;
    }


    protected function buildNewGoogleAPIPersonFromDTO(GoogleAPIContactDTO $dto): Person
    {
        if (!$dto->getClientyIdFromCustomFields()) {
            throw new MissingClientyIdInContactDTOException();
        }
        $params = $dto->getGooglePeopleServicePersonParams();
        $person = new Person($params);
        return $person;
    }


    protected function getPeopleService(?GoogleAPIUserToken $googlePeopleAPIUserToken): PeopleService
    {
        if (!$googlePeopleAPIUserToken) {
            throw new UserGoogleAPITokenNotFoundException();
        }
        $googleClient = $this->googleCommonAPIService->getGoogleClientFromGoogleUserToken($googlePeopleAPIUserToken);
        $service = new PeopleService($googleClient);
        return $service;
    }


}