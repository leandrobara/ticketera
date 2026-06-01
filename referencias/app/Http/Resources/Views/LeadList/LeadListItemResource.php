<?php

namespace App\Http\Resources\Views\LeadList;

use App\Http\Resources\UserResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\LandingResource;
use App\Http\Resources\LeadContactResource;
use App\Http\Resources\TagResourceCollection;
use App\Http\Resources\NoteResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\AcquisitionChannelResource;
use App\Http\Resources\LeadContactResourceCollection;
use App\Http\Resources\LeadCustomFieldResourceCollection;


class LeadListItemResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [
            'id' => $this->resource->id,
            'method' => $this->resource->method,
            'company' => $this->resource->company,
            'website' => $this->resource->website,
            'message' => $this->resource->message,
            'quality' => $this->resource->quality,
            'created_at' => $this->resource->created_at,
            'utm_source' => $this->resource->utm_source,
            'utm_medium' => $this->resource->utm_medium,
            'utm_content' => $this->resource->utm_content,
            'utm_campaign' => $this->resource->utm_campaign,
            'utm_keywords' => $this->resource->utm_keywords,
            'country_code' => $this->resource->country_code,
            'lead_created_at' => $this->resource->lead_created_at,
            'is_wap_bot_chat' => $this->resource->is_wap_bot_chat,
            'is_bulk_created' => $this->resource->is_bulk_created,
            'is_whatsapp_form' => $this->resource->is_whatsapp_form,
            'is_facebook_form' => $this->resource->is_facebook_form,
            'is_from_make_app' => $this->resource->is_from_make_app,
            'is_from_zapier_app' => $this->resource->is_from_zapier_app,
            'is_manually_created' => $this->resource->is_manually_created,
            'is_from_zapier_webhook' => $this->resource->is_from_zapier_webhook,
            'is_from_integration_api' => $this->resource->is_from_integration_api,
        ];
        $response = $this->loadUser($response);
        $response = $this->loadClient($response);
        $response = $this->loadLanding($response);
        $response = $this->loadStatus($response);
        $response = $this->loadAcquisitionChannel($response);
        $response = $this->loadTags($response);
        $response = $this->loadLeadContacts($response);
        // $response = $this->loadMainLeadContact($response);
        $response = $this->loadNotes($response);
        $response = $this->loadLeadCustomFieldValues($response);
        return $response;
    }


    private function loadUser(array $response)
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = ['id', 'type', 'username', 'name', 'last_name', 'email', 'phone'];
        // $userRs = new UserResource($this->resource->user);
        // $userRs->setVisibleFields($visibleFields);
        // $response['user'] = $userRs;
        $response['user'] = $this->resource->user->only($visibleFields);
        return $response;
    }


    private function loadNotes(array $response)
    {
        if (!$this->resource->relationLoaded('notes')) {
            $this->resource->load('notes');
        }
        // $response['notes'] = new NoteResourceCollection($this->resource->notes);
        $response['notes'] = $this->resource->notes->toArray();
        return $response;
    }


    private function loadClient(array $response)
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }
        $visibleFields = ['id', 'name'];
        // $clientRs = new ClientResource($this->resource->client);
        // $clientRs->setVisibleFields($visibleFields);
        // $response['client'] = $clientRs;
        $response['client'] = $this->resource->client->only($visibleFields);
        return $response;
    }


    private function loadLanding(array $response)
    {
        if (!$this->resource->relationLoaded('landing')) {
            $this->resource->load('landing');
        }
        $visibleFields = ['id', 'leads_landing_id', 'url'];
        // $landingRs = new LandingResource($this->resource->landing);
        // $landingRs->setVisibleFields($visibleFields);
        // $response['landing'] = $landingRs;
        $response['landing'] = $this->resource->landing ? $this->resource->landing->only($visibleFields) : null;
        return $response;
    }


    private function loadStatus(array $response)
    {
        if (!$this->resource->relationLoaded('status')) {
            $this->resource->load('status');
        }
        $visibleFields = [
            'id',
            'client_id',
            'name',
            'category',
            'hash',
            'text_color',
            'background_color',
            'sale_probability',
            'order',
        ];
        // $statusRs = new StatusResource($this->resource->status);
        // $statusRs->setVisibleFields($visibleFields);
        // $response['status'] = $statusRs;
        $response['status'] = $this->resource->status->only($visibleFields);
        return $response;
    }


    private function loadAcquisitionChannel(array $response)
    {
        if (!$this->resource->relationLoaded('acquisitionChannel')) {
            $this->resource->load('acquisitionChannel');
        }
        $visibleFields = ['id', 'client_id', 'name', 'text_color', 'background_color'];
        // $acquisitionChannelRs = new AcquisitionChannelResource($this->resource->acquisitionChannel);
        // $acquisitionChannelRs->setVisibleFields($visibleFields);
        // $response['acquisitionChannel'] = $acquisitionChannelRs;
        $response['acquisitionChannel'] = $this->resource->acquisitionChannel
            ? $this->resource->acquisitionChannel->only($visibleFields)
            : null
        ;
        return $response;
    }


    private function loadTags(array $response)
    {
        if (!$this->resource->relationLoaded('tags')) {
            $this->resource->load('tags');
        }
        $visibleFields = ['id', 'name', 'category', 'text_color', 'background_color'];
        // $tagCollectionRs = new TagResourceCollection($this->resource->tags);
        // $tagCollectionRs->setVisibleFields($visibleFields);
        // $response['tags'] = $tagCollectionRs;
        $response['tags'] = $this->resource->tags->toArray();
        return $response;
    }


    private function loadLeadContacts(array $response)
    {
        if (!$this->resource->relationLoaded('leadContacts')) {
            $this->resource->load('leadContacts');
        }
        $visibleFields = ['id', 'name', 'last_name', 'role', 'is_main', 'leadContactEmails', 'leadContactPhones'];
        // $leadContactsRs = new LeadContactResourceCollection($this->resource->leadContacts);
        // $leadContactsRs->setVisibleFields($visibleFields);
        // $response['leadContacts'] = $leadContactsRs;
        $response['leadContacts'] = $this->resource->leadContacts->map(function ($leadContact) use ($visibleFields) {
            return $leadContact->only($visibleFields);
        })->toArray();
        return $response;
    }


    private function loadMainLeadContact(array $response)
    {
        if (!$this->resource->relationLoaded('leadContacts')) {
            $this->resource->load('leadContacts');
        }
        $leadContact = $this->resource->leadContacts->filter(function ($leadContact) {
            return $leadContact->is_main;
        })->first();
        $visibleFields = ['id', 'name', 'last_name', 'role', 'is_main', 'leadContactEmails', 'leadContactPhones'];
        // $leadContactRs = new LeadContactResource($leadContact);
        // $leadContactRs->setVisibleFields($visibleFields);
        // $response['mainLeadContact'] = $leadContactRs;
        $response['mainLeadContact'] = $leadContact->only($visibleFields);
        return $response;
    }


    private function loadLeadCustomFieldValues(array $response)
    {
        if (!$this->resource->relationLoaded('leadCustomFieldsValues')) {
            $this->resource->load('leadCustomFieldsValues');
        }
        if (!$this->resource->client->relationLoaded('leadsCustomFields')) {
            $this->resource->client->load('leadsCustomFields');
        }
        $leadsCustomFields = $this->resource->client->leadsCustomFields;
        $leadCustomFieldsValues = $this->resource->leadCustomFieldsValues->keyBy('lead_custom_field_id');

        $visibleFields = ['id', 'name', 'type', 'order', 'is_shown_in_leads_row'];
        // $fieldsRs = new LeadCustomFieldResourceCollection($this->resource->client->leadsCustomFields);
        // $fieldsRs->setLeadCustomFieldValues($this->resource->leadCustomFieldsValues);
        // $fieldsRs->setVisibleFields($visibleFields);
        // $response['leadCustomFields'] = $fieldsRs;

        $response['leadCustomFields'] = $leadsCustomFields->map(
            function ($lcf) use ($visibleFields, $leadCustomFieldsValues) {
                $leadCustomFieldValue = $leadCustomFieldsValues->get($lcf->id);
                $leadCustomFieldArray = $lcf->only($visibleFields);

                $leadCustomFieldArray['leadCustomFieldValue'] = null;
                if ($leadCustomFieldValue) {
                    $leadCustomFieldArray['leadCustomFieldValue'] = [
                        'id' => $leadCustomFieldValue->id,
                        'hash' => $leadCustomFieldValue->hash,
                        'value' => $leadCustomFieldValue->value,
                    ];
                }
                return $leadCustomFieldArray;
            }
        )->toArray();
        return $response;
    }

}
