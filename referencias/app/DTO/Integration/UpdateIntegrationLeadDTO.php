<?php

namespace App\DTO\Integration;

use Illuminate\Support\Collection;


class UpdateIntegrationLeadDTO
{

    protected $firstEmail = null;
    protected $firstPhone = null;
    protected $secondEmail = null;
    protected $secondPhone = null;

    protected $leadAttrs = [];
    protected $customFields = [];
    protected $leadContactAttrs = [];
    

    public static function buildFromRequestArray(array $attrs): UpdateIntegrationLeadDTO
    {
        $dto = new UpdateIntegrationLeadDTO();
        $dto->setCustomFields($attrs);
        $dto->setLeadDataFromRequestArray($attrs);
        $dto->setLeadContactDataFromRequestArray($attrs);
        $dto->setLeadContactPhoneDataFromRequestArray($attrs);
        $dto->setLeadContactEmailDataFromRequestArray($attrs);
        return $dto;
    }


    public function setLeadDataFromRequestArray(array $attrs): UpdateIntegrationLeadDTO
    {
        if (key_exists('acquisitionChannelId', $attrs)) {
            $this->leadAttrs['acquisition_channel_id'] = $attrs['acquisitionChannelId'];
        }
        if (key_exists('quality', $attrs)) {
            $this->leadAttrs['quality'] = $attrs['quality'];
        }
        if (key_exists('company', $attrs)) {
            $this->leadAttrs['company'] = $attrs['company'];
        }
        return $this;
    }


    public function setLeadContactDataFromRequestArray(array $attrs): UpdateIntegrationLeadDTO
    {
        if (key_exists('name', $attrs)) {
            $this->leadContactAttrs['name'] = $attrs['name'];
        }
        if (key_exists('lastName', $attrs)) {
            $this->leadContactAttrs['last_name'] = $attrs['lastName'];
        }
        return $this;
    }


    public function setLeadContactPhoneDataFromRequestArray(array $attrs): UpdateIntegrationLeadDTO
    {
        $this->firstPhone = $attrs['phone'] ?? null;
        $this->secondPhone = $attrs['phone2'] ?? null;
        if (!$this->firstPhone && $this->secondPhone) {
            $this->firstPhone = $this->secondPhone;
            $this->secondPhone = null;
        }
        return $this;
    }


    public function setLeadContactEmailDataFromRequestArray(array $attrs): UpdateIntegrationLeadDTO
    {
        $this->firstEmail = $attrs['email'] ?? null;
        $this->secondEmail = $attrs['email2'] ?? null;
        if (!$this->firstEmail && $this->secondEmail) {
            $this->firstEmail = $this->secondEmail;
            $this->secondEmail = null;
        }
        return $this;
    }


    public function setCustomFields(array $attrs): UpdateIntegrationLeadDTO
    {
        $this->customFields = ($attrs['customFields'] ?? []) ? $attrs['customFields'] : [];
        return $this;
    }


    public function getLeadAttrs(): array
    {
        return $this->leadAttrs;
    }


    public function getLeadContactAttrs(): array
    {
        return $this->leadContactAttrs;
    }


    public function getLeadFirstEmail(): ?string
    {
        return $this->firstEmail;
    }


    public function getLeadSecondEmail(): ?string
    {
        return $this->secondEmail;
    }


    public function getLeadFirstPhone(): ?string
    {
        return $this->firstPhone;
    }


    public function getLeadSecondPhone(): ?string
    {
        return $this->secondPhone;
    }


    public function getCustomFields(): Collection
    {
        return collect($this->customFields);
    }

}
