<?php

namespace App\DTO\Integration;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;


class CreateNewIntegrationLeadDTO
{
    protected $fbclid;
    protected $utm_source;
    protected $utm_medium;
    protected $utm_content;
    protected $utm_campaign;
    protected $utm_keywords;

    protected $notes = [];
    protected $method = null;
    protected $company = null;
    protected $message = null;
    protected $clientId = null;
    protected $otherFields = [];
    protected $customFields = [];
    protected $landingUrl = null;
    protected $isFromZapierWebhook = false;
    protected $acquisitionChannelId = null;
    protected $mainLeadContactName = null;
    protected $mainLeadContactEmail = null;
    protected $mainLeadContactPhone = null;
    protected $mainLeadContactEmail2 = null;
    protected $mainLeadContactPhone2 = null;
    protected $isFromIntegrationApi = false;
    protected $mainLeadContactLastName = null;


    public static function buildFromRequestArray(array $reqArray): CreateNewIntegrationLeadDTO
    {
        $dto = new self();
        $dto->loadUtms($reqArray);
        $dto->setNotes($reqArray);
        $dto->setCustomFields($reqArray);
        $dto->setLeadDataFromRequestArray($reqArray);
        $dto->setMainLeadContactDataFromRequestArray($reqArray);

        return $dto;
    }


    public function setLeadDataFromRequestArray(array $reqArray): CreateNewIntegrationLeadDTO
    {
        $attrs = $reqArray['lead'];
        $this->method = ($attrs['method'] ?? null) ? $attrs['method'] : null;
        $this->message = ($attrs['message'] ?? null) ? $attrs['message'] : null;
        $this->company = ($attrs['company'] ?? null) ? $attrs['company'] : null;
        $this->clientId = ($attrs['clientId'] ?? null) ? $attrs['clientId'] : null;
        $this->otherFields = ($attrs['otherFields'] ?? []) ? $attrs['otherFields'] : [];
        $this->landingUrl = ($attrs['landing']['url'] ?? null) ? $attrs['landing']['url'] : null;
        $this->isFromZapierWebhook = ($attrs['isFromZapierWebhook'] ?? false)
            ? $attrs['isFromZapierWebhook']
            : false;
        $this->isFromIntegrationApi = ($attrs['isFromIntegrationApi'] ?? false)
            ? $attrs['isFromIntegrationApi']
            : false
        ;
        $this->acquisitionChannelId = ($attrs['acquisitionChannelId'] ?? null)
            ? $attrs['acquisitionChannelId']
            : null
        ;

        return $this;
    }


    public function setMainLeadContactDataFromRequestArray(array $reqArray): CreateNewIntegrationLeadDTO
    {
        $attrs = $reqArray['lead'];
        $this->mainLeadContactName = ($attrs['name'] ?? null) ? $attrs['name'] : null;
        $this->mainLeadContactEmail = ($attrs['email'] ?? null) ? $attrs['email'] : null;
        $this->mainLeadContactPhone = ($attrs['phone'] ?? null) ? $attrs['phone'] : null;
        $this->mainLeadContactEmail2 = ($attrs['email2'] ?? null) ? $attrs['email2'] : null;
        $this->mainLeadContactPhone2 = ($attrs['phone2'] ?? null) ? $attrs['phone2'] : null;
        $this->mainLeadContactLastName = ($attrs['lastName'] ?? null) ? $attrs['lastName'] : null;
        return $this;
    }


    public function setNotes(array $reqArray): CreateNewIntegrationLeadDTO
    {
        $attrs = $reqArray['lead'];
        $this->notes = ($attrs['notes'] ?? []) ? $attrs['notes'] : [];
        return $this;
    }


    public function setCustomFields(array $reqArray): CreateNewIntegrationLeadDTO
    {
        $attrs = $reqArray['lead'];
        $this->customFields = ($attrs['customFields'] ?? []) ? $attrs['customFields'] : [];
        return $this;
    }


    public function getLeadAttrs(): array
    {
        $leadAttrs = [
            'method' => $this->method,
            'company' => $this->company,
            'message' => $this->message,
            'client_id' => $this->clientId,
            'other_fields' => $this->getFormattedOtherFields(),
            'is_from_zapier_webhook' => $this->isFromZapierWebhook,
            'acquisition_channel_id' => $this->acquisitionChannelId,
            'is_from_integration_api' => $this->isFromIntegrationApi,
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


    public function getNotes(): Collection
    {
        return collect($this->notes);
    }


    public function getCustomFields(): Collection
    {
        return collect($this->customFields);
    }


    protected function getFormattedOtherFields()
    {
        $i = 1;
        $formattedOtherFields = [];
        foreach ($this->otherFields as $otherFieldArr) {
            $formattedOtherFields["$i"] = $otherFieldArr;
            $i++;
        }
        return $formattedOtherFields;
    }


    public function getLandingUrl(): ?string
    {
        return $this->landingUrl;
    }


    protected function loadUtms(array $reqArray): CreateNewIntegrationLeadDTO
    {
        $attrs = $reqArray['lead'];
        $this->fbclid = ($attrs['fbclid'] ?? null) ? $attrs['fbclid'] : null;
        $this->utm_source = ($attrs['utm_source'] ?? null) ? $attrs['utm_source'] : null;
        $this->utm_medium = ($attrs['utm_medium'] ?? null) ? $attrs['utm_medium'] : null;
        $this->utm_content = ($attrs['utm_content'] ?? null) ? $attrs['utm_content'] : null;
        $this->utm_campaign = ($attrs['utm_campaign'] ?? null) ? $attrs['utm_campaign'] : null;
        $this->utm_keywords = ($attrs['utm_keywords'] ?? null) ? $attrs['utm_keywords'] : null;

        // Hack para integración con FB/Calendly, fbclid lo puedo haber mandado contenido en utm_campaign
        $utmCampaignHasFBCLID = Str::contains($this->utm_campaign, ';fbclid=');
        if ($utmCampaignHasFBCLID) {
            $this->fbclid = trim(Str::after($this->utm_campaign, ';fbclid='));
            $this->utm_campaign = trim(Str::before($this->utm_campaign, ';fbclid='));
            $this->utm_campaign = ($this->utm_campaign ?? null) ? $this->utm_campaign : null;
        }
        return $this;
    }

}
