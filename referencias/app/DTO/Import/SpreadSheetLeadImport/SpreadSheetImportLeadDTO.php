<?php

namespace App\DTO\Import\SpreadSheetLeadImport;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\DTO\Import\ImportLeadDTOInterface;
use App\DTO\Import\SpreadSheetLeadImport\SpreadSheetImportLeadContactDTO;


class SpreadSheetImportLeadDTO implements ImportLeadDTOInterface
{

    public $notes = null;
    public $method = null;
    public $tagNames = [];
    public $company = null;
    public $message = null;
    public $contacts = null;
    public $userName = null;
    public $statusName = null;
    public $acquisitionChannelName = null;
    
    public $tags = [];
    public $user = null;
    public $status = null;
    public $customFields = [];
    public $acquisitionChannel = null;
    
    public $notFoundTagNames = [];
    public $statusWasFound = false;
    public $channelWasFound = false;
    public $tagNameIsTooLong = false;
    public $longLeadContactNames = [];
    public $longLeadContactLastNames = [];
    public $leadContactNameIsTooLong = false;
    public $leadContactLastNameIsTooLong = false;

    public $isPersistable = true;
    public $nonPersistableReasons = [];

    const DUPLICATED = 'duplicated';
    const NON_EXISTENT_USER = 'non_existent_user';
    const EMPTY_MAIN_CONTACT = 'empty_main_contact';
    const NON_EXISTENT_STATUS = 'non_existent_status';


    public function __construct(array $arrayLead, array $headers)
    {
        $arrayLead = $this->cleanArrayValues($arrayLead);

        $this->method = 'form';
        $this->notes = trim($arrayLead[4]);
        $this->company = trim($arrayLead[2]);
        $this->message = trim($arrayLead[3]);
        $this->userName = trim($arrayLead[6]);
        $this->statusName = trim($arrayLead[1]);
        $this->acquisitionChannelName = $arrayLead[0];
        $this->tagNames = $this->standarizeTagNames(explode(';', $arrayLead[5]));
        
        $contactsFields = array_slice($arrayLead, 7, 6 * 4);
        $this->contacts = $this->loadContacts($contactsFields);
        $this->contacts = $this->cleanDuplicatedEmails($this->contacts);
        $this->contacts = $this->cleanDuplicatedPhones($this->contacts);
        $this->contacts = $this->cleanContacts($this->contacts);

        // ['name' => 'xxx', 'value' => 'xxx', 'found' => false, 'lead_custom_field_id' => null]
        // LeadsBulkUploadService::fillPreviewDTOLeadCustomFields le setea 'lead_custom_field_id' y 'found'
        $this->customFields = $this->getCustomFields($headers, $arrayLead);
    }


    public function getLeadAttrs(): array
    {
        return [
            'company' => $this->company,
            'method' => $this->method,
            'message' => $this->message
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
            'email' =>  $mainContact->emails[0] ?? null,
            'email2' => $mainContact->emails[1] ?? null,
            'phone' => $mainContact->phones[0] ?? null,
            'phone2' => $mainContact->phones[1] ?? null,
            'invalid_emails' => $mainContact->invalidEmails,
            'invalid_phones' => $mainContact->invalidPhones,
            'nameIsTooLong' => $mainContact->leadContactNameIsTooLong,
            'lastNameIsTooLong' => $mainContact->leadContactLastNameIsTooLong,
        ];
    }


    public function fixMainContactEmailsAndPhones(): void
    {
        $mainContact = $this->contacts->first();
        if (!$mainContact) {
            return;
        }

        $mainEmail = trim(strtolower($mainContact->emails[0] ?? ''));
        $secEmail = trim(strtolower($mainContact->emails[1] ?? ''));
        $mainPhone = trim(strtolower($mainContact->phones[0] ?? ''));
        $secPhone = trim(strtolower($mainContact->phones[1] ?? ''));

        if (!$mainEmail && $secEmail) {
            $mainContact->emails[0] = $secEmail;
            $mainContact->emails[1] = null;
        }
        if (!$mainPhone && $secPhone) {
            $mainContact->phones[0] = $secPhone;
            $mainContact->phones[1] = null;
        }
    }


    public function mainContactIsEmpty(): bool
    {
        $mainContactArr = $this->getMainContactAttrs();
        if (!$mainContactArr) {
            return true;
        }

        $isNotEmpty = (
            $mainContactArr['email'] ||
            $mainContactArr['phone'] ||
            $mainContactArr['email2'] ||
            $mainContactArr['phone2'] ||
            ($mainContactArr['name'] && !$mainContactArr['nameIsTooLong']) ||
            ($mainContactArr['last_name'] && !$mainContactArr['lastNameIsTooLong'])
        );
        return !$isNotEmpty;
    }


    public function getNonPersistableReasons(): array
    {
        return $this->nonPersistableReasons;
    }


    public function addNonPersistibleReason(string $reason): SpreadSheetImportLeadDTO
    {
        $allowedReasons = [
            self::DUPLICATED,
            self::EMPTY_MAIN_CONTACT,
            self::NON_EXISTENT_USER,
            self::NON_EXISTENT_STATUS,
        ];

        if (!in_array($reason, $allowedReasons)) {
            throw new Exception('SpreadSheetImportLeadDTO invalid import status');
        }
        $this->nonPersistableReasons[] = $reason;
        return $this;
    }


    private function standarizeTagNames(array $tagNames): array
    {
        $standarizedTagNames = [];
        foreach ($tagNames as $tagName) {
            if ($tagName) {
                $standarizedTagNames[] = ucfirst(strtolower(trim($tagName)));
            }
        }
        return $standarizedTagNames;
    }


    private function loadContacts(array $contactsFields): Collection
    {
        $i = 0;
        $dtos = new Collection();
        $total = count($contactsFields);
        // jump 6 to 6
        while ($i < $total) {
            $dto = new SpreadSheetImportLeadContactDTO(array_slice($contactsFields, $i, $i + 6));
            // First contact is ALWAYS added, cause it can NEVER BE EMPTY.
            if ($i === 0 || $dto->isNotEmpty()) {
                $dtos->add($dto);
            }
            $i += 6;
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


    private function cleanDuplicatedEmails(Collection $contacts): Collection
    {
        $existentEmails = collect([]);
        foreach ($contacts as $contactDTO) {
            foreach ($contactDTO->emails as $i => $email) {
                if (!$email) {
                    continue;
                }
                $email = strtolower($email);
                if (!$existentEmails->contains($email)) {
                    $existentEmails->push($email);
                    $contactDTO->emails[$i] = $email;
                    continue;
                }
                // If email already exists for this lead, I remove it.
                unset($contactDTO->emails[$i]);
            }
        }
        return $contacts;
    }


    private function cleanDuplicatedPhones(Collection $contacts): Collection
    {
        $existentPhones = collect([]);
        foreach ($contacts as $contactDTO) {
            foreach ($contactDTO->phones as $i => $phone) {
                if (!$phone) {
                    continue;
                }
                $phone = strtolower($phone);
                $contactDTO->phones[$i] = $phone;
                if (!$existentPhones->contains($phone)) {
                    $existentPhones->push($phone);
                    $contactDTO->phones[$i] = $phone;
                    continue;
                }
                // If phone already exists for this lead, I remove it.
                unset($contactDTO->phones[$i]);
            }
        }
        return $contacts;
    }


    private function cleanArrayValues($arrayValues)
    {
        $arrayValues = array_map(function ($val) {
            if (is_string($val)) {
                // Limpio los caracteres extraños (códigos ASCII menores que 32 y mayores que 126)
                $val = preg_replace('/[\x00-\x1F\x7F-\xFF&&[^áéíóúÁÉÍÓÚÜü]]/', '', $val);
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
        for ($i = 31; $i <= 40; $i++) {
            $customFieldHeaderName = $headers[$i] ?? 'XXXX';
            if (Str::contains($customFieldHeaderName, 'XXXX')) {
                continue;
            }
            $customFieldName = trim(Str::after($customFieldHeaderName, 'Campo personalizado: '));
            if (!$customFieldName) {
                continue;
            }
            $value = trim($arrayLead[$i]);
            $customFields[] = [
                'found' => false,
                'value' => $value,
                'name' => $customFieldName,
                'lead_custom_field_id' => null,
            ];
        }
        return $customFields;
    }

}
