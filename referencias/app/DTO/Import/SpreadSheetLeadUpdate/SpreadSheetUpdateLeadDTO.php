<?php

namespace App\DTO\Import\SpreadSheetLeadUpdate;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\DTO\Import\UpdateLeadDTOInterface;
use App\DTO\Import\SpreadSheetLeadUpdate\SpreadSheetUpdateLeadContactDTO;


class SpreadSheetUpdateLeadDTO implements UpdateLeadDTOInterface
{

    public $notes = null;
    public $leadId = null;
    public $method = null;
    public $company = null;
    public $contacts = null;
    public $userName = null;
    public $acquisitionChannelName = null;
    
    public $lead = null;
    public $user = null;
    public $customFields = [];
    public $acquisitionChannel = null;
    
    public $userWasFound = false;
    public $leadWasFound = false;
    public $leadIdDuplicated = [];
    public $channelWasFound = false;
    public $isLeadIdDuplicated = false;
    public $customFieldsAreNotEnabled = false;
    public $leadMainContactIsDuplicated = false;

    public $emails = [];
    public $phones = [];
    
    public $warningReasons = [];
    public $nonPersistableReasons = [];

    const LEAD_ID_IS_EMPTY = 'lead_id_is_empty';
    const NON_EXISTENT_LEAD = 'non_existent_lead';
    const NON_EXISTENT_USER = 'non_existent_user';
    const NON_EXISTENT_CHANNEL = 'non_existent_channel';
    const LEAD_ID_IS_DUPLICATED = 'lead_id_is_duplicated';
    const NON_EXISTENT_CUSTOM_FIELD = 'non_existent_custom_field';
    const LEAD_CONTACT_INVALID_EMAIL = 'lead_contact_invalid_email';
    const LEAD_CONTACT_INVALID_PHONE = 'lead_contact_invalid_phone';
    const LEAD_CONTACT_NAME_IS_TO_LONG = 'lead_contact_name_is_to_long';
    const CUSTOM_FIELDS_ARE_NOT_ENABLED = 'custom_fields_are_not_enabled';
    const LEAD_MAIN_CONTACT_ALREADY_EXISTS = 'lead_main_contact_already_exists';
    const LEAD_CONTACT_EMAIL_ALREADY_EXISTS = 'lead_contact_email_already_exists';
    const LEAD_CONTACT_PHONE_ALREADY_EXISTS = 'lead_contact_phone_already_exists';
    const LEAD_CONTACT_LAST_NAME_IS_TO_LONG = 'lead_contact_last_name_is_to_long';


    public function __construct(array $arrayLead, array $headers)
    {
        $arrayLead = $this->cleanArrayValues($arrayLead);

        $this->method = 'form';
        $this->notes = trim($arrayLead[3]);
        $this->leadId = trim($arrayLead[0]);
        $this->company = trim($arrayLead[2]);
        $this->userName = trim($arrayLead[4]);
        $this->acquisitionChannelName = $arrayLead[1];
        
        $contactsFields = array_slice($arrayLead, 5, 6 * 4);
        $this->contacts = $this->loadContacts($contactsFields);
        $this->contacts = $this->cleanContacts($this->contacts);
        $this->customFields = $this->getCustomFields($headers, $arrayLead);
    }


    public function getLeadAttrs(): array
    {
        return [
            'company' => $this->company,
            'method' => $this->method,
        ];
    }


    public function getMainContactAttrs(): array
    {
        $mainContact = $this->contacts->first();
        if (!$mainContact) {
            return [];
        }

        return [
            'name' => $mainContact->name ?? null,
            'last_name' => $mainContact->lastName ?? null,
            'email' =>  $mainContact->email ?? null,
            'phone' => $mainContact->phone ?? null,
            'invalid_email' => $mainContact->invalidEmail,
            'invalid_phone' => $mainContact->invalidPhone,
        ];
    }


    public function isEmptyMainContactAttrs(): bool
    {
        $mainLeadContactAttrs = $this->getMainContactAttrs();

        $name = $mainLeadContactAttrs['name'] ?? '';
        $email = $mainLeadContactAttrs['email'] ?? '';
        $phone = $mainLeadContactAttrs['phone'] ?? '';
        $lastName = $mainLeadContactAttrs['last_name'] ?? '';

        return (!$name && !$email && !$phone && !$lastName) ? true : false;
    }


    public function leadIdIsEmpty(): bool
    {
        return $this->leadId ? false : true;
    }


    public function getNonPersistableReasons(): array
    {
        return $this->nonPersistableReasons;
    }


    public function getWarningReasons(): array
    {
        return $this->warningReasons;
    }


    public function addNonPersistibleReason(string $reason): SpreadSheetUpdateLeadDTO
    {
        $allowedReasons = [
            self::LEAD_ID_IS_EMPTY,
            self::NON_EXISTENT_LEAD,
            self::LEAD_ID_IS_DUPLICATED,
            self::LEAD_MAIN_CONTACT_ALREADY_EXISTS,
        ];

        if (!in_array($reason, $allowedReasons)) {
            throw new Exception('SpreadSheetUpdateLeadDTO invalid update status');
        }
        $this->nonPersistableReasons[] = $reason;
        return $this;
    }


    public function addWarningReason(string $reason): SpreadSheetUpdateLeadDTO
    {
        $allowedReasons = [
            self::NON_EXISTENT_USER,
            self::NON_EXISTENT_CHANNEL,
            self::NON_EXISTENT_CUSTOM_FIELD,
            self::LEAD_CONTACT_INVALID_EMAIL,
            self::LEAD_CONTACT_INVALID_PHONE,
            self::LEAD_CONTACT_NAME_IS_TO_LONG,
            self::CUSTOM_FIELDS_ARE_NOT_ENABLED,
            self::LEAD_CONTACT_LAST_NAME_IS_TO_LONG,
            self::LEAD_CONTACT_EMAIL_ALREADY_EXISTS,
            self::LEAD_CONTACT_PHONE_ALREADY_EXISTS,
        ];

        if (!in_array($reason, $allowedReasons)) {
            throw new Exception('SpreadSheetUpdateLeadDTO invalid update warning status');
        }
        $this->warningReasons[] = $reason;
        return $this;
    }


    private function loadContacts(array $contactsFields): Collection
    {
        $i = 0;
        $to = 7;
        $dtos = new Collection();
        $total = count($contactsFields);

        while ($i < $total) {
            $dto = new SpreadSheetUpdateLeadContactDTO(array_slice($contactsFields, $i, $i + $to));
            if ($i === 0 || $dto->isNotEmpty()) {
                $dtos->add($dto);
            }
            $i += $to;
        }
        return $dtos;
    }


    private function cleanContacts(Collection $contacts): Collection
    {
        $existentEmails = collect([]);
        foreach ($contacts as $i => $contactDTO) {
            if ($contactDTO->isEmpty()) {
                $contacts->forget($i);
            }
        }
        return $contacts;
    }


    private function cleanArrayValues($arrayValues)
    {
        $arrayValues = array_map(function ($val) {
            if (is_string($val)) {
                return trim($val);
            }
            return $val;
        }, $arrayValues);
        // $arrayValues = array_map("trim", $arrayValues);

        // remove \n\s to certain fields (status, company, user)
        $arrayValues[1] = preg_replace("/\r|\n/", "", $arrayValues[1]);
        $arrayValues[2] = preg_replace("/\r|\n/", "", $arrayValues[2]);
        $arrayValues[6] = preg_replace("/\r|\n/", "", $arrayValues[6]);
        return $arrayValues;
    }


    private function getCustomFields(array $headers, array $arrayLead): array
    {
        $customFields = [];
        for ($i = 9; $i <= 18; $i++) {
            $customFieldHeaderName = $headers[$i] ?? 'XXXX';
            if (Str::contains($customFieldHeaderName, 'XXXX')) {
                continue;
            }
            $customFieldName = trim(Str::after($customFieldHeaderName, 'Campo personalizado: '));
            if (!$customFieldName) {
                continue;
            }
            $customFieldValue = trim($arrayLead[$i]);
            if (!$customFieldValue) {
                continue;
            }
            // No chequea si existe o no existe todavia, solo parsea y obtiene nombre y valor.
            $customFields[] = [
                'found' => false,
                'name' => $customFieldName,
                'value' => $customFieldValue,
                'lead_custom_field_id' => null,
            ];
        }
        return $customFields;
    }

}
