<?php

namespace App\DTO;

use Illuminate\Support\Collection;


class CreateNewManualLeadDTO
{
    protected $tags = [];
    protected $notes = [];
    protected $userId = null;
    protected $message = null;
    protected $company = null;
    protected $statusId = null;
    protected $customFields = [];
    protected $acquisitionChannelId = null;

    protected $mainLeadContactName = null;
    protected $mainLeadContactEmail = null;
    protected $mainLeadContactPhone = null;
    protected $mainLeadContactEmail2 = null;
    protected $mainLeadContactPhone2 = null;
    protected $mainLeadContactLastName = null;


    public static function buildFromRequestArray(array $reqArray): CreateNewManualLeadDTO
    {
        $dto = new self();
        $dto->setLeadDataFromRequestArray($reqArray);
        $dto->setMainLeadContactDataFromRequestArray($reqArray);

        return $dto;
    }


    public function setLeadDataFromRequestArray(array $reqArray): CreateNewManualLeadDTO
    {
        $this->company = ($reqArray['company'] ?? null) ? $reqArray['company'] : null;
        $this->message = ($reqArray['message'] ?? null) ? $reqArray['message'] : null;
        $this->userId = ($reqArray['user_id'] ?? null) ? $reqArray['user_id'] : null;
        $this->statusId = ($reqArray['status_id'] ?? null) ? $reqArray['status_id'] : null;
        $this->acquisitionChannelId = ($reqArray['acquisition_channel_id'] ?? null)
            ? $reqArray['acquisition_channel_id']
            : null
        ;
        return $this;
    }


    public function setTags(Collection $tags): CreateNewManualLeadDTO
    {
        $this->tags = $tags;
        return $this;
    }


    public function setCustomFields(Collection $customFields): CreateNewManualLeadDTO
    {
        $this->customFields = $customFields;
        return $this;
    }


    public function setNotes(Collection $notes): CreateNewManualLeadDTO
    {
        $this->notes = $notes;
        return $this;
    }


    public function setMainLeadContactDataFromRequestArray(array $reqArray): CreateNewManualLeadDTO
    {
        $attrs = $reqArray['mainLeadContact'];
        $this->mainLeadContactName = $attrs['name'];
        $this->mainLeadContactEmail = ($attrs['email'] ?? null) ? $attrs['email'] : null;
        $this->mainLeadContactPhone = ($attrs['phone'] ?? null) ? $attrs['phone'] : null;
        $this->mainLeadContactEmail2 = ($attrs['email2'] ?? null) ? $attrs['email2'] : null;
        $this->mainLeadContactPhone2 = ($attrs['phone2'] ?? null) ? $attrs['phone2'] : null;
        $this->mainLeadContactLastName = ($attrs['last_name'] ?? null) ? $attrs['last_name'] : null;

        return $this;
    }


    public function getNewLeadAttrs(): array
    {
        return [
            'user_id' => $this->userId,
            'company' => $this->company,
            'message' => $this->message,
            'status_id' => $this->statusId,
            'acquisition_channel_id' => $this->acquisitionChannelId,
        ];
    }


    public function getMainLeadContactAttrs(): array
    {
        return [
            'name' => $this->mainLeadContactName,
            'email' => $this->mainLeadContactEmail,
            'phone' => $this->mainLeadContactPhone,
            'email2' => $this->mainLeadContactEmail2,
            'phone2' => $this->mainLeadContactPhone2,
            'last_name' => $this->mainLeadContactLastName,
        ];
    }


    public function getTags(): Collection
    {
        return $this->tags ? $this->tags : collect($this->tags);
    }


    public function getCustomFields(): Collection
    {
        return $this->customFields ? $this->customFields : collect($this->customFields);
    }


    public function getNotes(): Collection
    {
        return $this->notes ? $this->notes : collect($this->notes);
    }
   
}
