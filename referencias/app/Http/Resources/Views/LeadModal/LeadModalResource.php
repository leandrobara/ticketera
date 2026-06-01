<?php

namespace App\Http\Resources\Views\LeadModal;

use App\Http\Resources\UserResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\LandingResource;
use App\Http\Resources\LeadContactResource;
use App\Http\Resources\TagResourceCollection;
use App\Http\Resources\NoteResourceCollection;
use App\Http\Resources\TaskResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\AcquisitionChannelResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\LeadContactResourceCollection;
use App\Http\Resources\LeadCustomFieldResourceCollection;
use App\Http\Resources\Views\LeadAttachment\LeadAttachmentResourceCollection;


class LeadModalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [
            'id' => $this->id,
            'method' => $this->method,
            'fbclid' => $this->fbclid,
            'company' => $this->company,
            'quality' => $this->quality,
            'website' => $this->website,
            'message' => $this->message,
            'created_at' => $this->created_at,
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'landed_url' => $this->landed_url,
            'utm_content' => $this->utm_content,
            'utm_campaign' => $this->utm_campaign,
            'utm_keywords' => $this->utm_keywords,
            'country_code' => $this->country_code,
            'is_bulk_created' => $this->is_bulk_created,
            'lead_created_at' => $this->lead_created_at,
            'is_wap_bot_chat' => $this->is_wap_bot_chat,
            'is_facebook_form' => $this->is_facebook_form,
            'is_whatsapp_form' => $this->is_whatsapp_form,
            'other_fields' => $this->other_fields ?: null,
            'is_from_make_app' => $this->is_from_make_app,
            'is_from_zapier_app' => $this->is_from_zapier_app,
            'is_manually_created' => $this->is_manually_created,
            'is_from_zapier_webhook' => $this->is_from_zapier_webhook,
            'tracking_parameters' => $this->tracking_parameters ?: null,
            'is_from_integration_api' => $this->is_from_integration_api,
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
        $response = $this->loadTasks($response);
        $response = $this->loadLeadSales($response);
        $response = $this->loadProposalsInfo($response);
        $response = $this->loadLeadCustomFieldValues($response);
        $response = $this->loadGoogleAPIUserContact($response);
        $response = $this->loadLeadAttachments($response);
        return $response;
    }


    private function loadLeadAttachments(array $response)
    {
        if (!$this->resource->relationLoaded('leadAttachments')) {
            $this->resource->load('leadAttachments');
        }

        $visibleFields = ['id', 'lead_id', 'bucket_filepath', 'original_filename', 'size'];
        $leadAttachmentRs = new LeadAttachmentResourceCollection($this->resource->leadAttachments);
        $leadAttachmentRs->setVisibleFields($visibleFields);
        $response['leadAttachments'] = $leadAttachmentRs;

        return $response;
    }


    private function loadUser(array $response)
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = [
            'id',
            'type',
            'name',
            'email',
            'phone',
            'username',
            'last_name',
            'wapi_is_synced',
            'wapi_session_phone_number',
            'wap_sender_session_phone_number',
        ];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);
        $response['user'] = $userRs;

        return $response;
    }


    private function loadClient(array $response)
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }

        $visibleFields = ['id', 'name'];
        $clientRs = new ClientResource($this->resource->client);
        $clientRs->setVisibleFields($visibleFields);
        $response['client'] = $clientRs;

        return $response;
    }


    private function loadLanding(array $response)
    {
        if (!$this->resource->relationLoaded('landing')) {
            $this->resource->load('landing');
        }

        $visibleFields = ['id', 'leads_landing_id', 'url'];
        $landingRs = new LandingResource($this->resource->landing);
        $landingRs->setVisibleFields($visibleFields);
        $response['landing'] = $landingRs;

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
        $statusRs = new StatusResource($this->resource->status);
        $statusRs->setVisibleFields($visibleFields);
        $response['status'] = $statusRs;

        return $response;
    }


    private function loadAcquisitionChannel(array $response)
    {
        if (!$this->resource->relationLoaded('acquisitionChannel')) {
            $this->resource->load('acquisitionChannel');
        }

        $visibleFields = ['id', 'client_id', 'name', 'text_color', 'background_color'];
        $acquisitionChannelRs = new AcquisitionChannelResource($this->resource->acquisitionChannel);
        $acquisitionChannelRs->setVisibleFields($visibleFields);
        $response['acquisitionChannel'] = $acquisitionChannelRs;

        return $response;
    }


    private function loadTags(array $response)
    {
        if (!$this->resource->relationLoaded('tags')) {
            $this->resource->load('tags');
        }

        $visibleFields = ['id', 'name', 'category', 'text_color', 'background_color'];
        $tagCollectionRs = new TagResourceCollection($this->resource->tags);
        $tagCollectionRs->setVisibleFields($visibleFields);

        $response['tags'] = $tagCollectionRs;

        return $response;
    }


    private function loadLeadContacts(array $response)
    {
        if (!$this->resource->relationLoaded('leadContacts')) {
            $this->resource->load('leadContacts');
        }

        $visibleFields = [
            'id',
            'name',
            'last_name',
            'role',
            'is_main',
            'order',
            'leadContactEmails',
            'leadContactPhones',
        ];
        $leadContactsRs = new LeadContactResourceCollection($this->resource->leadContacts);
        $leadContactsRs->setVisibleFields($visibleFields);

        $response['leadContacts'] = $leadContactsRs;

        return $response;
    }


    private function loadMainLeadContact(array $response)
    {
        if (!$this->resource->relationLoaded('mainLeadContact')) {
            $this->resource->load('mainLeadContact');
        }

        $leadContact = $this->resource->mainLeadContact;
        $visibleFields = [
            'id',
            'name',
            'last_name',
            'role',
            'is_main',
            'order',
            'leadContactEmails',
            'leadContactPhones',
        ];
        $leadContactRs = new LeadContactResource($leadContact);
        $leadContactRs->setVisibleFields($visibleFields);

        $response['mainLeadContact'] = $leadContactRs;

        return $response;
    }


    private function loadNotes(array $response)
    {
        if (!$this->resource->relationLoaded('notes')) {
            $this->resource->load('notes');
        }
        $notesRs = new NoteResourceCollection($this->resource->notes);
        $notesRs->setVisibleFields([
            'id',
            'text',
            'created_at',
            'updated_at',
            'audionote_bucket_hash',
            'audionote_bucket_name',
            'audionote_transcription',
            'audionote_bucket_filepath',
            'audionote_bucket_file_size',
            'audionote_bucket_file_extension',
        ]);
        $response['notes'] = $notesRs;
        return $response;
    }


    private function loadTasks(array $response)
    {
        if (!$this->resource->relationLoaded('tasks')) {
            $this->resource->load('tasks');
        }

        $visibleFields = [
            'id',
            'user',
            'title',
            'status',
            'description',
            'limit_date',
            'is_important',
            'created_at',
            'updated_at',
        ];
        $rs = new TaskResourceCollection($this->resource->tasks);
        $rs->setVisibleFields($visibleFields);
        $response['tasks'] = $rs;
        return $response;
    }


    private function loadLeadSales(array $response)
    {
        if (!$this->resource->relationLoaded('leadSales')) {
            $this->resource->load('leadSales');
        }
        $leadSales = $this->resource->leadSales->sortByDesc('sale_date');
        $response['leadSales'] = $leadSales->toArray();
        return $response;
    }


    private function loadProposalsInfo(array $response)
    {
        if (!$this->resource->relationLoaded('proposalsInfo')) {
            $this->resource->load('proposalsInfo');
        }
        $proposalsInfo = $this->resource->proposalsInfo->sortByDesc('sent_date');
        foreach ($proposalsInfo as $proposalInfo) {
            $proposalInfo->isWhatsAppMetaAPISending = false;
            $wapMsgs = $proposalInfo->whatsAppSendingMessages ?? collect();
            if ($wapMsgs->isNotEmpty() && $wapMsgs->every(fn($m) => !empty($m->meta_id))) {
                $proposalInfo->isWhatsAppMetaAPISending = true;
            }
        }
        $response['proposalsInfo'] = $proposalsInfo->toArray();
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
        $fields = ['id', 'name', 'type', 'order', 'type_values', 'is_shown_in_leads_row', 'leadCustomFieldValue'];
        $fieldsRs = new LeadCustomFieldResourceCollection($this->resource->client->leadsCustomFields);
        $fieldsRs->setLeadCustomFieldValues($this->resource->leadCustomFieldsValues);
        $fieldsRs->setVisibleFields($fields);
        $response['leadCustomFields'] = $fieldsRs;
        return $response;
    }


    private function loadGoogleAPIUserContact(array $response)
    {
        if (!$this->resource->relationLoaded('googleAPIUserContacts')) {
            $this->resource->load('googleAPIUserContacts');
        }
        $googleAPIUserContacts = $this->resource->googleAPIUserContacts;
        // Este dato se pasa usando el método additional() de JsonResource
        $response['googleAPIUserContact'] = null;
        $loginUser = $this->additional['loginUser'] ?? null;
        if ($loginUser) {
            $response['googleAPIUserContact'] = $googleAPIUserContacts->filter(function ($c) use ($loginUser) {
                return $c->user_id == $loginUser->id;
            })->first();
        }
        return $response;
    }

}
