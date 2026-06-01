<?php

namespace App\DTO\Integration;

use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;


class CreateNewIntegrationZapierAppLeadDTO
{
    protected $note;
    protected $company;
    protected $message;
    protected $otherFields = [];
    protected $customFields = [];
    protected $landingUrl = null;
    protected $acquisitionChannel;
    protected $mainLeadContactName;
    protected $mainLeadContactEmail;
    protected $mainLeadContactPhone;
    protected $mainLeadContactEmail2;
    protected $mainLeadContactPhone2;
    protected $mainLeadContactLastName;

    protected $fbclid;
    protected $utm_source;
    protected $utm_medium;
    protected $utm_content;
    protected $utm_campaign;
    protected $utm_keywords;


    public static function buildFromRequestArray(array $reqArray): CreateNewIntegrationZapierAppLeadDTO
    {
        $dto = new CreateNewIntegrationZapierAppLeadDTO();
        
        $attrs = $reqArray['lead'];

        $dto->loadUtms($attrs);
        $dto->note = ($attrs['note'] ?? []) ? $attrs['note'] : [];
        $dto->acquisitionChannel = $attrs['acquisitionChannel'] ?? null;
        $dto->message = ($attrs['message'] ?? null) ? $attrs['message'] : null;
        $dto->company = ($attrs['company'] ?? null) ? $attrs['company'] : null;
        $dto->mainLeadContactName = ($attrs['name'] ?? null) ? $attrs['name'] : null;
        $dto->otherFields = ($attrs['otherFields'] ?? []) ? $attrs['otherFields'] : [];
        $dto->mainLeadContactEmail = ($attrs['email'] ?? null) ? $attrs['email'] : null;
        $dto->mainLeadContactPhone = ($attrs['phone'] ?? null) ? $attrs['phone'] : null;
        $dto->landingUrl = ($attrs['landingUrl'] ?? null) ? $attrs['landingUrl'] : null;
        $dto->customFields = ($attrs['customFields'] ?? []) ? $attrs['customFields'] : [];
        $dto->mainLeadContactEmail2 = ($attrs['email2'] ?? null) ? $attrs['email2'] : null;
        $dto->mainLeadContactPhone2 = ($attrs['phone2'] ?? null) ? $attrs['phone2'] : null;
        $dto->mainLeadContactLastName = ($attrs['lastName'] ?? null) ? $attrs['lastName'] : null;

        return $dto;
    }


    public function getLeadAttrs(): array
    {
        $leadAttrs = [
            'method' => 'form',
            'company' => $this->company,
            'message' => $this->message,
            'is_from_zapier_app' => true,
            'other_fields' => $this->getFormattedOtherFieldsArray(),
            'acquisition_channel_id' => $this->acquisitionChannel?->id,
        ];

        if ($this->fbclid) {
            $leadAttrs['fbclid'] = $this->fbclid;
        }
        if ($this->utm_source) {
            $leadAttrs['utm_source'] = $this->utm_source;
        }
        if ($this->utm_medium) {
            $leadAttrs['utm_medium'] = $this->utm_medium;
        }
        if ($this->utm_content) {
            $leadAttrs['utm_content'] = $this->utm_content;
        }
        if ($this->utm_campaign) {
            $leadAttrs['utm_campaign'] = $this->utm_campaign;
        }
        if ($this->utm_keywords) {
            $leadAttrs['utm_keywords'] = $this->utm_keywords;
        }

        return $leadAttrs;
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


    protected function loadUtms(array $data): CreateNewIntegrationZapierAppLeadDTO
    {
        $this->fbclid = ($data['fbclid'] ?? null) ? $data['fbclid'] : null;
        $this->utm_source = ($data['utm_source'] ?? null) ? $data['utm_source'] : null;
        $this->utm_medium = ($data['utm_medium'] ?? null) ? $data['utm_medium'] : null;
        $this->utm_content = ($data['utm_content'] ?? null) ? $data['utm_content'] : null;
        $this->utm_campaign = ($data['utm_campaign'] ?? null) ? $data['utm_campaign'] : null;
        $this->utm_keywords = ($data['utm_keywords'] ?? null) ? $data['utm_keywords'] : null;

        // Hack para integración con FB/Calendly, fbclid lo puedo haber mandado contenido en utm_campaign
        $utmCampaignHasFBCLID = Str::contains($this->utm_campaign, ';fbclid=');
        if ($utmCampaignHasFBCLID) {
            $this->fbclid = trim(Str::after($this->utm_campaign, ';fbclid='));
            $this->utm_campaign = trim(Str::before($this->utm_campaign, ';fbclid='));
            $this->utm_campaign = ($this->utm_campaign ?? null) ? $this->utm_campaign : null;
        }
        return $this;
    }


    public function getNotes(): Collection
    {
        return $this->note ? new Collection([$this->note]) : new Collection([]);
    }


    public function getCustomFields(): Collection
    {
        return collect($this->customFields);
    }


    public function getLandingUrl(): ?string
    {
        return $this->landingUrl;
    }


    protected function getFormattedOtherFieldsArray(): array
    {
        $i = 1;
        $formattedOtherFields = [];
        foreach ($this->otherFields as $otherFieldArr) {
            $formattedOtherFields["$i"] = $otherFieldArr;
            $i++;
        }
        return $formattedOtherFields;
    }

}
