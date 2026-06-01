<?php

namespace App\DTO\Import;

use Carbon\Carbon;
use Illuminate\Support\Str;


class LeadsLeadDTO implements ImportLeadDTOInterface
{

    public $name = null;
    public $email = null;
    public $phone = null;
    public $fbclid = null;
    public $method = null;
    public $leadsId = null;
    public $message = null;
    public $company = null;
    public $channel = null;
    public $lastName = null;
    public $createdAt = null;
    public $landedUrl = null;
    public $utm_source = null;
    public $utm_medium = null;
    public $landingUrl = null;
    public $utm_content = null;
    public $otherFields = null;
    public $utm_campaign = null;
    public $utm_keywords = null;
    public $leadsClientId = null;
    public $leadsLandingId = null;
    public $isWhatsAppForm = null;
    public $leadsLeadNumber = null;
    public $serializedFields = null;


    public static function buildFromLeadsLeadData(array $leadsLeadData): LeadsLeadDTO
    {
        $dto = new LeadsLeadDTO();
        $dto->name = $leadsLeadData['userName'];
        $dto->method = $leadsLeadData['method'];
        $dto->message = $leadsLeadData['message'];
        $dto->email = $leadsLeadData['userEmail'];
        $dto->phone = $leadsLeadData['userPhone'];
        $dto->channel = $leadsLeadData['channel'];
        $dto->leadsId = (int) $leadsLeadData['id'];
        $dto->createdAt = $leadsLeadData['createdAt'];
        $dto->company = $leadsLeadData['userCompany'];
        $dto->landingUrl = $leadsLeadData['landingUrl'];
        $dto->fbclid = $leadsLeadData['fbclid'] ?? null;
        $dto->lastName = $leadsLeadData['userLastName'];
        $dto->otherFields = $leadsLeadData['otherFields'];
        $dto->serializedFields = $leadsLeadData['formField'];
        $dto->leadsLeadNumber = $leadsLeadData['queryNumber'];
        $dto->leadsClientId = (int) $leadsLeadData['clientId'];
        $dto->utm_source = $leadsLeadData['utm_source'] ?? null;
        $dto->utm_medium = $leadsLeadData['utm_medium'] ?? null;
        $dto->leadsLandingId = (int) $leadsLeadData['landingId'];
        $dto->utm_content = $leadsLeadData['utm_content'] ?? null;
        $dto->landedUrl = $leadsLeadData['firstLandedUrl'] ?? null;
        $dto->utm_campaign = $leadsLeadData['utm_campaign'] ?? null;
        $dto->utm_keywords = $leadsLeadData['utm_keywords'] ?? null;
        $dto->isWhatsAppForm = (bool) $leadsLeadData['isWhatsAppForm'];

        $dto->email = filter_var($dto->email, FILTER_VALIDATE_EMAIL) ?: '';
        return $dto;
    }


    public function getMainLeadContactAttrs(): array
    {
        return [
            'email' => $this->email ?: null,
            'name' => $this->name ?: null,
            'phone' => $this->phone ?: null,
            'last_name' => $this->lastName ?: null,
        ];
    }


    public function hasContactInfo()
    {
        return $this->name || $this->email || $this->phone || $this->lastName;
    }


    public function getContactInfoString()
    {
        $str = "";
        $str .= $this->leadsId ? "Leads Lead ID: {$this->leadsId}" : "";
        $str .= $this->name ? " | Nombre: {$this->name}" : "";
        $str .= $this->lastName ? " | Apellido: {$this->lastName}" : "";
        $str .= $this->email ? " | Email: {$this->email}" : "";
        if (!$str) {
            $str .= $this->phone ? " | Teléfono: {$this->phone}" : "";
        }
        return $str;
    }


    public function getLeadAttrs(): array
    {
        $otherFields = explode('- ', $this->otherFields);
        $otherFields = array_filter($otherFields, function ($f) {
            return trim($f);
        });
        $otherFields = array_map(function ($f) {
            $name = trim(Str::before($f, ': '));
            $value = trim(Str::after($f, ': '));
            return ['name' => $name, 'value' => $value];
        }, $otherFields);

        $createdDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->createdAt, 'America/Argentina/Buenos_Aires');
        return [
            'method' => $this->method,
            'company' => $this->company,
            'other_fields' => $otherFields,
            'fbclid' => $this->fbclid ?: null,
            'leads_query_id' => $this->leadsId,
            'message' => $this->message ?: null,
            'landed_url' => $this->landedUrl ?: null,
            'utm_source' => $this->utm_source ?: null,
            'utm_medium' => $this->utm_medium ?: null,
            'utm_content' => $this->utm_content ?: null,
            'is_whatsapp_form' => $this->isWhatsAppForm,
            'utm_campaign' => $this->utm_campaign ?: null,
            'utm_keywords' => $this->utm_keywords ?: null,
            'leads_lead_number' => $this->leadsLeadNumber ?: null,
            'serialized_fields' => $this->serializedFields ?: null,
            'lead_created_at' => $createdDate->setTimezone('UTC'),
        ];
    }


    public function getLandingAttrs(): array
    {
        return [
            'url' => $this->landingUrl ?: null,
            'leads_landing_id' => $this->leadsLandingId ?: null,
        ];
    }


    public function getAcquisitionChannelAttrs(): array
    {
        return ['name' => $this->channel ?: null];
    }


    public function getClientAttrs(): array
    {
        return ['leads_client_id' => $this->leadsClientId];
    }

}
