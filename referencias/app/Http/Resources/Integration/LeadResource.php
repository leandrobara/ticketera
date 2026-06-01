<?php

namespace App\Http\Resources\Integration;

use App\Models\Lead;
use App\Models\LeadContact;
use Illuminate\Support\Collection;


class LeadResource
{

    private $lead = null;


    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }


    public function toArray()
    {
        $user = $this->loadUser();
        $tags = $this->loadTags();
        $sales = $this->loadSales();
        $status = $this->loadStatus();
        $landing = $this->loadLanding();
        $lastSale = $this->loadLastSale();
        $mainLeadContact = $this->loadMainLeadContact();
        $otherFields = $this->loadLeadOtherFieldsValues();
        $leadContactEmails = $this->loadLeadContactEmails();
        $leadContactPhones = $this->loadLeadContactPhones();
        $acquisitionChannel = $this->loadAcquisitionChannel();
        $customFieldValues = $this->loadLeadCustomFieldValues();

        $email = $leadContactEmails->get(0);
        $phone = $leadContactPhones->get(0);
        $email2 = $leadContactEmails->get(1);
        $phone2 = $leadContactPhones->get(1);
        $createdDate = $this->lead->lead_created_at;
        
        $formattedFBClickID = null;
        if ($this->lead->fbclid) {
            $tsMillis = $createdDate->modify('5 minutes ago')->getTimestamp() * 1000;
            $formattedFBClickID = "fb.1.{$tsMillis}.{$this->lead->fbclid}";
        }

        $response = [
            // Identificación
            'id' => $this->lead->id,
            'name' => $mainLeadContact->name ?? null,
            'lastName' => $mainLeadContact->last_name ?? null,
            'company' => $this->lead->company ?? null,
            'quality' => $this->lead->quality ?? 0,
            'createdDate' => $createdDate->format('Y-m-d\TH:i:sO'),
        
            // Contacto
            'email' => $email ? $email->email : null,
            'email2' => $email2 ? $email2->email : null,
            'phone' => $phone ? $phone->phone : null,
            'phone2' => $phone2 ? $phone2->phone : null,
            'message' => $this->lead->message ?? null,
                
            // Estado y gestión
            'user' => $user ?? null,
            'tags' => $tags ?? [],
            'status' => $status ?? null,
        
            // Otros campos
            'otherFields' => $otherFields ?? [],
            'customFields' => $customFieldValues ?? [],

            // Datos comerciales
            'sales' => $sales ?? [],
            'lastSale' => $lastSale ?? null,

            // Origen / adquisición
            'landing' => $landing ?? null,
            'acquisitionChannel' => $acquisitionChannel ?? null,
            'utmSource' => $this->lead->utm_source ?? null,
            'utmMedium' => $this->lead->utm_medium ?? null,
            'utmContent' => $this->lead->utm_content ?? null,
            'utmCampaign' => $this->lead->utm_campaign ?? null,
            'utmKeywords' => $this->lead->utm_keywords ?? null,
            'tracking_parameters' => $this->lead->tracking_parameters ?? null,
            '_fbc' => $formattedFBClickID,
            'fbclid' => $this->lead->fbclid ?? null,
            'ctwa_clid' => $this->lead->tracking_parameters['ctwa_clid'] ?? null,
            'fb_form_lead_id' => $this->lead->tracking_parameters['fb_form_lead_id'] ?? null,
        ];
        return $response;
    }


    private function loadLeadOtherFieldsValues(): array
    {
        if ($this->lead->other_fields) {
            $otherFields = collect($this->lead->other_fields)->map(function ($otherField) {
                return $otherField;
            });
            return $otherFields->values()->all();
        }
        return [];
    }


    private function loadTags(): array
    {
        if (!$this->lead->relationLoaded('tags')) {
            $this->lead->load('tags');
        }
        if ($this->lead->tags->isNotEmpty()) {
            $tags = $this->lead->tags->map(function ($tag) {
                return ['id' => $tag->id, 'name' => $tag->name];
            });
            return $tags->values()->all();
        }
        return [];
    }


    private function loadStatus(): ?array
    {
        if (!$this->lead->relationLoaded('status')) {
            $this->lead->load('status');
        }
        if ($this->lead->status) {
            $status = $this->lead->status;
            return ['id' => $status->id, 'name' => $status->name];
        }
        return null;
    }


    private function loadSales(): array
    {
        if (!$this->lead->relationLoaded('leadSales')) {
            $this->lead->load('leadSales');
        }
        if ($this->lead->leadSales) {
            $leadSales = $this->lead->leadSales->sortByDesc('id');
            $sales = $leadSales->map(function ($leadSale) {
                return [
                    'id' => $leadSale->id,
                    'amount' => (int) $leadSale->amount,
                    'description' => $leadSale->description,
                    'saleDate' => $leadSale->sale_date->format('Y-m-d\TH:i:sO'),
                ];
            });
            return $sales->values()->all();
        }
        return [];
    }


    private function loadLastSale(): ?array
    {
        if (!$this->lead->relationLoaded('leadSales')) {
            $this->lead->load('leadSales');
        }
        if ($this->lead->leadSales->isNotEmpty()) {
            $leadSale = $this->lead->leadSales->sortByDesc('id')->first();
            return [
                'id' => $leadSale->id,
                'amount' => (int) $leadSale->amount,
                'description' => $leadSale->description,
                'saleDate' => $leadSale->sale_date->format('Y-m-d\TH:i:sO'),
            ];
        }
        return null;
    }


    private function loadLeadContactEmails(): Collection
    {
        if (!$this->lead->relationLoaded('mainLeadContact')) {
            $this->lead->load('mainLeadContact');
        }
        if (!$this->lead->relationLoaded('leadContactEmails')) {
            $this->lead->load('leadContactEmails');
        }

        $leadMainEmails = $this->lead->mainLeadContact->leadContactEmails->sortBy('id');
        $leadAllEmails = $leadMainEmails->merge($this->lead->leadContactEmails)->unique();
        return $leadAllEmails;
    }


    private function loadLeadContactPhones(): Collection
    {
        if (!$this->lead->relationLoaded('mainLeadContact')) {
            $this->lead->load('mainLeadContact');
        }
        if (!$this->lead->relationLoaded('leadContactPhones')) {
            $this->lead->load('leadContactPhones');
        }

        $leadMainPhones = $this->lead->mainLeadContact->leadContactPhones->sortBy('id');
        $leadAllPhones = $leadMainPhones->merge($this->lead->leadContactPhones)->unique();
        return $leadAllPhones;
    }


    private function loadMainLeadContact(): ?LeadContact
    {
        if (!$this->lead->relationLoaded('mainLeadContact')) {
            $this->lead->load('mainLeadContact');
        }
        if ($this->lead->mainLeadContact) {
            return $this->lead->mainLeadContact;
        }
        return null;
    }


    private function loadLeadCustomFieldValues(): array
    {
        if (!$this->lead->relationLoaded('client.leadsCustomFields')) {
            $this->lead->load('client.leadsCustomFields');
        }

        $leadsCustomFields = $this->lead->client->leadsCustomFields;
        if ($leadsCustomFields) {
            if (!$this->lead->relationLoaded('leadCustomFieldsValues')) {
                $this->lead->load('leadCustomFieldsValues');
            }

            $leadCustomFieldsValues = $this->lead->leadCustomFieldsValues;
            $customFields = $leadsCustomFields->map(function ($customField) use ($leadCustomFieldsValues) {
                $customValue = $leadCustomFieldsValues->where('lead_custom_field_id', $customField->id)->first();
                $value = $customValue->value ?? null;
                return [
                    'value' => $value,
                    'name' => $customField->name,
                    'is_shown_in_leads_row' => $customField->is_shown_in_leads_row,
                ];
            });
            return $customFields->all();
        }
        return [];
    }


    private function loadAcquisitionChannel(): ?array
    {
        if (!$this->lead->relationLoaded('acquisitionChannel')) {
            $this->lead->load('acquisitionChannel');
        }
        if ($this->lead->acquisitionChannel) {
            $acquisitionChannel = $this->lead->acquisitionChannel;
            return ['id' => $acquisitionChannel->id, 'name' => $acquisitionChannel->name];
        }
        return null;
    }


    private function loadUser(): ?array
    {
        if (!$this->lead->relationLoaded('user')) {
            $this->lead->load('user');
        }
        if ($this->lead->user) {
            $user = $this->lead->user;
            return [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ];
        }
        return null;
    }


    private function loadLanding(): ?array
    {
        if (!$this->lead->relationLoaded('landing')) {
            $this->lead->load('landing');
        }

        if ($this->lead->landing) {
            $landing = $this->lead->landing;
            return ['id' => $landing->id, 'url' => $landing->url];
        }
        return null;
    }

}
