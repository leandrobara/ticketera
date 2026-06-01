<?php

namespace App\DTO\Import\SpreadSheetLeadUpdate;


class SpreadSheetUpdateLeadContactDTO
{

    public $name = null;
    public $email = null;
    public $phone = null;
    public $order = null;
    public $lastName = null;
    public $phoneContacts = [];
    public $invalidEmail = null;
    public $invalidPhone = null;
    public $leadContactNameIsTooLong = false;
    public $leadContactLastNameIsTooLong = false;
    public $leadContactEmailAlreadyExists = false;
    public $leadContactPhoneAlreadyExists = false;


    public function __construct(array $contactsArray)
    {
        $contactsArray = $this->cleanArrayInfo($contactsArray);

        $this->name = $contactsArray[0] ?? null;
        $this->lastName = $contactsArray[1] ?? null;
        
        if ($contactsArray[2] ?? null) {
            $this->email = $contactsArray[2];
            if (!$this->isValidEmail($this->email)) {
                $this->invalidEmail = $this->email;
            }
        }

        if ($contactsArray[3] ?? null) {
            $this->phone = $contactsArray[3];
            if (!$this->isValidPhoneNumber($this->phone)) {
                $this->invalidPhone = $this->phone;
            }
        }
    }


    private function cleanArrayInfo($arrayInfo): array
    {
        $filteredArray = array_map(function ($info) {
            $contact = trim($info);
            $contact = preg_replace("/\r|\n/", "", $info);
            return $contact ?: null;
        }, $arrayInfo);

        return $filteredArray;
    }


    public function isNotEmpty(): bool
    {
        return $this->name || $this->lastName || $this->email || $this->phone;
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


    private function isValidPhoneNumber(string $phone): bool
    {
        $regex = '/^[0-9+]+$/';
        $isValidPhoneNumber = preg_match($regex, $phone);
        return $isValidPhoneNumber;
    }

}
