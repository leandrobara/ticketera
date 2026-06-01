<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\Views\WhatsAppAttachment\WhatsAppAttachmentResource;


class WhatsAppTemplateResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('attachment', $visibleFields)) {
            $response = $this->loadAttachment($response);
        }

        if (in_array('WAutomationsSequenceStep', $visibleFields)) {
            $response = $this->loadWAutomationsSequenceStep($response);
        }
        if (in_array('WAutomationsProposalResendRule', $visibleFields)) {
            $response = $this->loadWAutomationsProposalResendRule($response);
        }
        if (in_array('user', $visibleFields) && $visibleFields) {
            $response = $this->loadUserField($response);
        }
        if (in_array('templateCategory', $visibleFields)) {
            $response = $this->loadTemplateCategoryField($response);
        }

        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadUserField(array $response): array
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = ['id', 'type', 'name', 'last_name', 'phone', 'email'];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);

        $response['user'] = $userRs;
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


    private function loadAttachment(array $response): array
    {
        if (!$this->resource->whatsapp_attachment_id) {
            $response['attachment'] = null;
            return $response;
        }

        $visibleFields = ['id', 'original_filename', 'size', 'extension'];
        $attachmentRs = new WhatsAppAttachmentResource($this->resource->attachment);
        $attachmentRs->setVisibleFields($visibleFields);
        $response['attachment'] = $attachmentRs;
        return $response;
    }


    private function loadWAutomationsSequenceStep(array $response): array
    {
        if (!$this->resource->relationLoaded('WAutomationsSequenceStep')) {
            $this->resource->load('WAutomationsSequenceStep');
        }
        $response['WAutomationsSequenceStep'] = $this->resource->WAutomationsSequenceStep;
        return $response;
    }


    private function loadWAutomationsProposalResendRule(array $response): array
    {
        if (!$this->resource->relationLoaded('WAutomationsProposalResendRule')) {
            $this->resource->load('WAutomationsProposalResendRule');
        }
        $response['WAutomationsProposalResendRule'] = $this->resource->WAutomationsProposalResendRule;
        return $response;
    }


    private function loadTemplateCategoryField(array $response): array
    {
        if (!$this->resource->relationLoaded('TemplateCategory')) {
            $this->resource->load('TemplateCategory');
        }

        $visibleFields = ['id', 'name', 'text_color', 'background_color'];
        $templateCatRs = new TemplateCategoryResource($this->resource->templateCategory);
        $templateCatRs->setVisibleFields($visibleFields);

        $response['templateCategory'] = $templateCatRs;
        return $response;
    }

}
