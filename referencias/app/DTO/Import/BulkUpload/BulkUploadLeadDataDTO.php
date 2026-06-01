<?php

namespace App\DTO\Import\BulkUpload;

use Exception;
use App\Models\Tag;
use App\Models\User;
use App\Models\Status;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;


class BulkUploadLeadDataDTO
{

    public $tags = [];
    public $user = null;
    public $notes = null;
    public $contacts = [];
    public $status = null;
    public $message = null;
    public $company = null;
    public $customFields = [];
    public $acquisitionChannel = null;


    private function __construct()
    {
    }


    public static function build(array $params): BulkUploadLeadDataDTO
    {
        $dto = new BulkUploadLeadDataDTO();
        $dto->validate($params);
        
        $dto->user = $params['user'];
        $dto->status = $params['status'];
        $dto->tags = $params['tags'] ?? [];
        $dto->contacts = $params['contacts'];
        $dto->notes = $params['notes'] ?? null;
        $dto->message = $params['message'] ?? null;
        $dto->company = $params['company'] ?? null;
        $dto->customFields = $params['customFields'] ?? [];
        $dto->acquisitionChannel = $params['acquisitionChannel'] ?? null;
        return $dto;
    }


    private function validate($params): BulkUploadLeadDataDTO
    {
        $tags = $params['tags'] ?? [];
        $user = $params['user'] ?? null;
        $status = $params['status'] ?? null;
        $contacts = $params['contacts'] ?? [];
        $customFields = $params['customFields'] ?? [];
        $acquisitionChannel = $params['acquisitionChannel'] ?? null;

        if ($user && !($user instanceof User)) {
            throw new Exception('Invalid user');
        }
        if ($acquisitionChannel && !($acquisitionChannel instanceof AcquisitionChannel)) {
            throw new Exception('Invalid acquisition channel');
        }
        if (!$status || !($status instanceof Status)) {
            throw new Exception('Invalid status');
        }
        if ($tags) {
            foreach ($tags as $tag) {
                if (!($tag instanceof Tag)) {
                    throw new Exception('Invalid tag');
                }
            }
        }
        if ($customFields) {
            foreach ($customFields as $customField) {
                if (!($customField instanceof CustomFieldDTO)) {
                    throw new Exception('Invalid lead custom field');
                }
            }
        }
        if (!$contacts) {
            throw new Exception('Empty lead contacts');
        }
        foreach ($contacts as $contact) {
            if (!array_filter($contact)) {
                throw new Exception('Invalid lead contact');
            }
        }
        return $this;
    }


    public function getNewLeadAttrs(): array
    {
        return [
            'company' => $this->company,
            'message' => $this->message,
            'user_id' => $this->user?->id,
            'status_id' => $this->status->id,
            'acquisition_channel_id' => $this->acquisitionChannel ? $this->acquisitionChannel->id : null,
        ];
    }


    public function getMainLeadContactAttrs(): array
    {
        return [
            'order' => 0,
            'is_main' => true,
            'name' => $this->contacts[0]['name'] ?? null,
            'email' => $this->contacts[0]['emails'][0] ?? null,
            'phone' => $this->contacts[0]['phones'][0] ?? null,
            'email2' => $this->contacts[0]['emails'][1] ?? null,
            'phone2' => $this->contacts[0]['phones'][1] ?? null,
            'last_name' => $this->contacts[0]['last_name'] ?? null,
        ];
    }


    public function getSecondaryLeadContactsAttrs(): array
    {
        $contacts = [];
        foreach ($this->contacts as $index => $contact) {
            if ($index === 0) {
                continue;
            }
            $contacts[] = [
                'order' => $index,
                'is_main' => false,
                'name' => $contact['name'] ?? null,
                'email' => $contact['emails'][0] ?? null,
                'phone' => $contact['phones'][0] ?? null,
                'email2' => $contact['emails'][1] ?? null,
                'phone2' => $contact['phones'][1] ?? null,
                'last_name' => $contact['last_name'] ?? null,
            ];
        }
        return $contacts;
    }

    
    public function getTags(): array
    {
        if (is_array($this->tags)) {
            return array_unique($this->tags);
        }
        return array_unique($this->tags->toArray());
    }

    
    public function getCustomFieldsDTOs(): array
    {
        if (is_array($this->customFields)) {
            return $this->customFields;
        }
        return $this->customFields->toArray();
    }

}
