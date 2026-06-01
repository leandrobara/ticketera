<?php

namespace App\DTO\Import\SpreadSheetLeadImport;

class SpreadSheetImportLeadUserDTO
{
    public $id = null;
    public $name = null;
    public $username = null;
    public $email = null;
    public $phone = null;

    /**
     * @param $data
     */
    public function __construct($data)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->phone = $data['phone'] ?? null;
    }

    public static function create($data)
    {
        return new SpreadSheetImportLeadUserDTO($data);
    }
}
