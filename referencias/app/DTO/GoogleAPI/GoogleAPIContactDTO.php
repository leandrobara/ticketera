<?php

namespace App\DTO\GoogleAPI;

use App\Models\Lead;
use App\Models\GoogleAPIUserContact;
use Google\Service\PeopleService\Person;


class GoogleAPIContactDTO
{

    const PREFIX = '[Clienty]';
    const CUSTOM_FIELD_SEARCH_VALUE = 'lead_id_';
    const CUSTOM_FIELD_USER_ID_KEY = 'clienty_user_id';
    const CUSTOM_FIELD_SEARCH_KEY = 'clienty_search_key';
    const CUSTOM_FIELD_CLIENTY_ID_KEY = 'clienty_lead_id';

    // ['name' => xxx, 'lastName' => xxx, 'prefix' => xxx]
    public $names = [];
    public $company = null;
    public $customFields = [];
    public $phoneNumbers = [];
    public $emailAddresses = [];
    public $resourceName = null;


    public static function buildFromGoogleAPIPerson(Person $person)
    {
        $dto = new GoogleAPIContactDTO();

        $dto->resourceName = $person->resourceName;
        $names = $person->getNames();
        foreach ($names as $name) {
            $dto->names[] = [
                'name' => $name->getGivenName(),
                'lastName' => $name->getFamilyName(),
                'prefix' => $name->getHonorificPrefix(),
            ];
        }

        $organizations = $person->getOrganizations();
        if ($organizations[0] ?? null) {
            $dto->company = $organizations[0]->getName();
        }
        
        $emailAddresses = $person->getEmailAddresses();
        foreach ($emailAddresses as $emailAddress) {
            $dto->emailAddresses[] = $emailAddress->getValue();
        }

        $phoneNumbers = $person->getPhoneNumbers();
        foreach ($phoneNumbers as $phoneNumber) {
            $dto->phoneNumbers[] = $phoneNumber->getValue();
        }
        
        $customFields = $person->getUserDefined();
        foreach ($customFields as $customField) {
            $dto->customFields[] = ['key' => $customField->getKey(), 'value' => $customField->getValue()];
        }

        return $dto;
    }


    public static function buildFromLead(Lead $lead): GoogleAPIContactDTO
    {
        $dto = new GoogleAPIContactDTO();

        $leadContacts = $lead->leadContacts;
        $mainLeadContact = $leadContacts->where('is_main', true)->first();
        $otherLeadContacts = $leadContacts->where('is_main', false);
        $leadContacts = $otherLeadContacts->prepend($mainLeadContact);
        foreach ($leadContacts as $i => $leadContact) {
            $hasAnyName = trim($leadContact->name) || trim($leadContact->last_name);
            $firstName = $leadContact->name ?: '';
            $firstName = $hasAnyName ? $firstName : 'Sin nombre';
            $dto->names[] = [
                'name' => $firstName,
                'prefix' => self::PREFIX,
                'lastName' => $leadContact->last_name ?: '',
            ];
        }
        // Fix: no puede haber más de un name cuando se llama al servicio People.create
        $dto->names = [$dto->names[0]];

        $dto->company = $lead->company ?: null;
        $dto->name = $leadContact->name ?: null;
        $dto->customFields[] = [
            'key' => self::CUSTOM_FIELD_SEARCH_KEY,
            'value' => self::CUSTOM_FIELD_SEARCH_VALUE . $lead->id,
        ];
        $dto->customFields[] = ['key' => self::CUSTOM_FIELD_CLIENTY_ID_KEY, 'value' => (string) $lead->id];
        $dto->customFields[] = ['key' => self::CUSTOM_FIELD_USER_ID_KEY, 'value' => (string) $lead->user->id];

        $dto->emailAddresses = $lead->leadContactEmails ? $lead->leadContactEmails->pluck('email')->toArray() : [];
        
        $dto->phoneNumbers = [];
        $countryCode = $lead->client->country_code;
        $clientSettings = $lead->client->clientSettings;
        if ($lead->leadContactPhones) {
            $dto->phoneNumbers = $lead->leadContactPhones
                ->map(function ($leadContactPhone) use ($countryCode, $clientSettings) {
                    return $leadContactPhone->getWhatsAppFormattedPhone($countryCode, $clientSettings);
                })
                ->filter()
                ->toArray()
            ;
        }

        return $dto;
    }


    public static function buildFromUserContactModel(GoogleAPIUserContact $model): GoogleAPIContactDTO
    {
        $dto = self::buildFromLead($model->lead);
        $dto->resourceName = $model->resource_name;
        return $dto;
    }


    public function getGooglePeopleServicePersonParams(): array
    {
        $params = [];
        $params['names'] = collect($this->names)->map(function ($n) {
            return ['honorificPrefix' => $n['prefix'], 'givenName' => $n['name'], 'familyName' => $n['lastName']];
        })->values()->toArray();
        
        $params['userDefined'] = collect($this->customFields)->map(function ($c) {
            return ['key' => $c['key'], 'value' => $c['value']];
        })->values()->toArray();
        
        if ($this->company) {
            $params['organizations'] = [
                ['name' => $this->company]
            ];
        }
        if ($this->emailAddresses) {
            $params['emailAddresses'] = collect($this->emailAddresses)->map(function ($e) {
                return ['value' => $e];
            })->values()->toArray();
        }
        if ($this->phoneNumbers) {
            $params['phoneNumbers'] = collect($this->phoneNumbers)->map(function ($p) {
                return ['value' => $p];
            })->values()->toArray();
        }
        return $params;
    }


    public function getClientyIdFromCustomFields(): ?int
    {
        foreach ($this->customFields as $customField) {
            if ($customField['key'] == self::CUSTOM_FIELD_CLIENTY_ID_KEY) {
                return intval($customField['value']);
            }
        }
        return null;
    }

}
