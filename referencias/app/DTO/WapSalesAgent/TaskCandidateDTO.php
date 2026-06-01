<?php

namespace App\DTO\WapSalesAgent;


class TaskCandidateDTO
{

    public $id;
    public $title;
    public $status;
    public $client;
    public $userId;
    public $leadId;
    public $userName;
    public $leadName;
    public $limitDate;
    public $isImportant;


    public static function build(array $data): self
    {
        return new self($data);
    }


    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->title = $data['title'] ?? null;
        $this->status = $data['status'] ?? null;
        $this->client = $data['client'] ?? null;
        $this->leadId = $data['leadId'] ?? null;
        $this->userId = $data['userId'] ?? null;
        $this->userName = $data['userName'] ?? null;
        $this->leadName = $data['leadName'] ?? null;
        $this->limitDate = $data['limitDate'] ?? null;
        $this->isImportant = $data['isImportant'] ?? null;
    }


    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'client' => $this->client,
            'userId' => $this->userId,
            'leadId' => $this->leadId,
            'leadName' => $this->leadName,
            'userName' => $this->userName,
            'limitDate' => $this->limitDate,
            'isImportant' => $this->isImportant,
        ];
    }

}
