<?php

namespace App\DTO\WapSalesAgent;

class LeadCandidateDTO
{

    public $id;
    public $name;
    public $email;
    public $phone;
    public $lastname;
    public $statusName;


    public static function build(array $data): self
    {
        return new self($data);
    }


    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->lastName = $data['lastName'] ?? null;
        $this->fullName = $data['fullName'] ?? null;
        $this->statusName = $data['statusName'] ?? null;
    }


    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'lastName' => $this->lastname,
            'fullName' => $this->fullName,
            'statusName' => $this->statusName,
        ];
    }

}
