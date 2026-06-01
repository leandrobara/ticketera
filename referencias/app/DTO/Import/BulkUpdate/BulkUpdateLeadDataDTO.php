<?php

namespace App\DTO\Import\BulkUpdate;

use DateTime;
use Exception;
use DateTimeZone;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\User;
use App\Models\Status;
use App\Models\Client;
use App\Models\LeadContactPhone;
use App\Models\LeadContactEmail;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;


class BulkUpdateLeadDataDTO
{

    public ?Lead $lead = null;
    public ?User $user = null;
    public ?string $notes = null;
    public ?string $company = null;
    public array $customFieldsDTOs = [];
    public array $mainLeadContact = []; // ['phone' => 'xxx', 'email' => 'xxx', 'name' => 'xxx', 'last_name' => 'xxx']
    public ?AcquisitionChannel $acquisitionChannel = null;


    private function __construct()
    {
    }


    public static function build(array $params): BulkUpdateLeadDataDTO
    {
        $dto = new BulkUpdateLeadDataDTO();
        $dto->lead = $params['lead'] ?? null;
        $dto->user = $params['user'] ?? null;
        $dto->notes = $params['notes'] ?? null;
        $dto->company = $params['company'] ?? null;
        $dto->mainLeadContact = $params['mainLeadContact'] ?? [];
        $dto->customFieldsDTOs = $params['customFieldsDTOs'] ?? [];
        $dto->acquisitionChannel = $params['acquisitionChannel'] ?? null;
        return $dto;
    }


    public function getLeadAttrsToUpdate(): array
    {
        $attrs = [];
        if ($this->company) {
            $attrs['company'] = $this->company;
        }
        if ($this->user) {
            $attrs['user_id'] = $this->user->id;
        }
        if ($this->acquisitionChannel) {
            $attrs['acquisition_channel_id'] = $this->acquisitionChannel->id;
        }
        return $attrs;
    }


    public function getMainLeadContactAttrsToUpdate(): array
    {
        $attrs = [];
        if ($this->mainLeadContact['name'] ?? null) {
            $attrs['name'] = $this->mainLeadContact['name'];
        }
        if ($this->mainLeadContact['last_name'] ?? null) {
            $attrs['last_name'] = $this->mainLeadContact['last_name'];
        }
        return $attrs;
    }


    public function getMainLeadContactPhoneValueToCreate(): string | null
    {
        return $this->mainLeadContact['phone'] ?? null;
    }


    public function getMainLeadContactEmailValueToCreate(): string | null
    {
        return $this->mainLeadContact['email'] ?? null;
    }


    public function getCustomFieldDTOsToUpdate(): array
    {
        return $this->customFieldsDTOs;
    }

}
