<?php

namespace App\DTO\Import\SpreadSheetLeadImport;


class SpreadSheetImportLeadContactDTO
{

    public $name = null;
    public $emails = [];
    public $phones = [];
    public $order = null;
    public $lastName = null;
    public $invalidEmails = [];
    public $invalidPhones = [];
    public $leadContactNameIsTooLong = false;
    public $leadContactLastNameIsTooLong = false;


    public function __construct(array $contactsArray)
    {
        $contactsArray = $this->cleanArrayInfo($contactsArray);

        $this->name = $contactsArray[0];
        $this->lastName = $contactsArray[1];
        if ($contactsArray[2]) {
            $this->emails[] = $contactsArray[2];
        }
        if ($contactsArray[3]) {
            $this->emails[] = $contactsArray[3];
        }
        if ($contactsArray[4]) {
            $this->phones[] = $contactsArray[4];
        }
        if ($contactsArray[5]) {
            $this->phones[] = $contactsArray[5];
        }

        foreach ($this->emails as $email) {
            if (!$this->isValidEmail($email)) {
                $this->invalidEmails[] = $email;
            }
        }
        $this->emails = collect($this->emails)->filter(fn ($e) => $this->isValidEmail($e))->values()->toArray();
    }


    private function cleanArrayInfo($arrayInfo): array
    {
        $filteredArray = array_map(function ($info) {
            $contact = trim($info);
            $contact = preg_replace("/\r|\n/", "", $info);
            return $contact;
        }, $arrayInfo);

        return $filteredArray;
    }


    public function isNotEmpty(): bool
    {
        return $this->name || $this->emails || $this->phones || $this->lastName;
    }


    public function isEmpty(): bool
    {
        return !$this->isNotEmpty();
    }


    private function isValidEmail(string $email): bool
    {
        $isValidEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
        return $isValidEmail;
    }

}
