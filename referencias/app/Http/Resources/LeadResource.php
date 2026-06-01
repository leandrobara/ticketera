<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class LeadResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if ($visibleFields && in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if ($visibleFields && in_array('status', $visibleFields)) {
            $response = $this->loadStatusField($response);
        }
        if ($visibleFields && in_array('landing', $visibleFields)) {
            $response = $this->loadLandingField($response);
        }
        if ($visibleFields && in_array('user', $visibleFields)) {
            $response = $this->loadUserField($response);
        }
        if ($visibleFields && in_array('acquisitionChannel', $visibleFields)) {
            $response = $this->loadAcquisitionChannelField($response);
        }
        if ($visibleFields && in_array('mainLeadContact', $visibleFields)) {
            $response = $this->loadMainLeadContact($response);
        }
        if ($visibleFields && in_array('tags', $visibleFields)) {
            $response = $this->loadLeadTags($response);
        }
        if ($visibleFields && in_array('leadContacts', $visibleFields)) {
            $response = $this->loadLeadContacts($response);
        }
        if ($visibleFields && in_array('emailDraft', $visibleFields)) {
            $response = $this->loadLeadEmailDraft($response);
        }
        if ($visibleFields && in_array('leadCustomFieldsValues', $visibleFields)) {
            $response = $this->loadLeadCustomFieldValues($response);
        }
        if ($visibleFields && in_array('notes', $visibleFields)) {
            $response = $this->loadNotes($response);
        }

        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadClientField(array $response): array
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }
        $visibleFields = ['id', 'name', 'subdomain','country_code', 'version'];
        $clientRs = new ClientResource($this->resource->client);
        $clientRs->setVisibleFields($visibleFields);
        
        $response['client'] = $clientRs;
        return $response;
    }


    private function loadStatusField(array $response): array
    {
        if (!$this->resource->relationLoaded('status')) {
            $this->resource->load('status');
        }
        $response['status'] = $this->resource->status;
        return $response;
    }


    private function loadLandingField(array $response): array
    {
        if (!$this->resource->relationLoaded('landing')) {
            $this->resource->load('landing');
        }
        $response['landing'] = $this->resource->landing;
        return $response;
    }


    private function loadUserField(array $response): array
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = [
            'id', 'type', 'name', 'last_name', 'phone', 'email', 'wapi_session_phone_number', 'wapi_is_synced'
        ];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);

        $response['user'] = $userRs;
        return $response;
    }


    private function loadAcquisitionChannelField(array $response): array
    {
        if (!$this->resource->relationLoaded('acquisitionChannel')) {
            $this->resource->load('acquisitionChannel');
        }
        $visibleFields = ['id', 'name', 'text_color', 'background_color'];
        $acquisitionChannelRs = new AcquisitionChannelResource($this->resource->acquisitionChannel);
        $acquisitionChannelRs->setVisibleFields($visibleFields);

        $response['acquisitionChannel'] = $acquisitionChannelRs;
        return $response;
    }


    private function loadMainLeadContact(array $response)
    {
        if (!$this->resource->relationLoaded('mainLeadContact')) {
            $this->resource->load('mainLeadContact');
        }
        $leadContact = $this->resource->mainLeadContact;
        $visibleFields = ['id', 'name', 'last_name', 'role', 'is_main', 'leadContactEmails', 'leadContactPhones'];
        $leadContactRs = new LeadContactResource($leadContact);
        $leadContactRs->setVisibleFields($visibleFields);

        $response['mainLeadContact'] = $leadContactRs;
        return $response;
    }


    private function loadLeadEmailDraft(array $response)
    {
        if (!$this->resource->relationLoaded('emailDraft')) {
            $this->resource->load('emailDraft');
        }

        $emailDraft = $this->resource->emailDraft;
        $visibleFields = ['id', 'subject', 'body'];
        $resource = new LeadContactResource($emailDraft);
        $resource->setVisibleFields($visibleFields);

        $response['emailDraft'] = $resource;
        return $response;
    }


    private function loadLeadContacts(array $response)
    {
        if (!$this->resource->relationLoaded('leadContacts')) {
            $this->resource->load('leadContacts');
        }

        $visibleFields = [
            'id', 'name', 'last_name', 'role', 'is_main', 'order', 'leadContactEmails', 'leadContactPhones',
        ];
        $leadContactsRs = new LeadContactResourceCollection($this->resource->leadContacts);
        $leadContactsRs->setVisibleFields($visibleFields);

        $response['leadContacts'] = $leadContactsRs;

        return $response;
    }


    private function loadLeadTags(array $response)
    {
        if (!$this->resource->relationLoaded('tags')) {
            $this->resource->load('tags');
        }
        $rs = new TagResourceCollection($this->resource->tags);
        $response['tags'] = $rs;
        return $response;
    }


    private function loadLeadCustomFieldValues(array $response)
    {
        if (!$this->resource->relationLoaded('leadCustomFieldsValues')) {
            $this->resource->load('leadCustomFieldsValues');
        }
        if (!$this->resource->relationLoaded('client.leadsCustomFields')) {
            $this->resource->load('client.leadsCustomFields');
        }
        $visibleFields = ['id', 'name', 'order', 'type', 'is_shown_in_leads_row', 'leadCustomFieldValue'];
        $fieldsRs = new LeadCustomFieldResourceCollection($this->resource->client->leadsCustomFields);
        $fieldsRs->setLeadCustomFieldValues($this->resource->leadCustomFieldsValues);
        $fieldsRs->setVisibleFields($visibleFields);
        
        $response['leadCustomFields'] = $fieldsRs;
        return $response;
    }


    private function loadNotes(array $response)
    {
        if (!$this->resource->relationLoaded('notes')) {
            $this->resource->load('notes');
        }
        $response['notes'] = new NoteResourceCollection($this->resource->notes);
        return $response;
    }

}
