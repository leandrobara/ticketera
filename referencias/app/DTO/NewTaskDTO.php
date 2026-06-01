<?php

namespace App\DTO;

use Illuminate\Support\Collection;


class NewTaskDTO
{

    protected $title = null;
    protected $status = null;
    protected $userId = null;
    protected $leadId = null;
    protected $clientId = null;
    protected $limitDate = null;
    protected $description = null;
    protected $isImportant = null;
    protected $automationLogId = null;
    

    public static function build($data): NewTaskDTO
    {
        $dto = new NewTaskDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->title = $data['title'];
        $this->userId = $data['user_id'];
        $this->leadId = $data['lead_id'];
        $this->clientId = $data['client_id'];
        $this->limitDate = $data['limit_date'];
        $this->status = $data['status'] ?? 'pending';
        $this->description = $data['description'] ?? '';
        $this->isImportant = $data['is_important'] ?? false;
        $this->automationLogId = $data['automation_log_id'] ?? null;
    }


    public function toArray(): array
    {
        $arr = [];
        $arr['title'] = $this->title;
        $arr['user_id'] = $this->userId;
        $arr['lead_id'] = $this->leadId;
        $arr['client_id'] = $this->clientId;
        $arr['limit_date'] = $this->limitDate;
        $arr['status'] = $this->status;
        $arr['description'] = $this->description;
        $arr['is_important'] = $this->isImportant;
        $arr['automation_log_id'] = $this->automationLogId;
        return $arr;
    }

}
