<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class EmailTemplateResource extends JsonResource
{
    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('user', $visibleFields)) {
            $response = $this->loadUserField($response);
        }
        if (in_array('attachments', $visibleFields)) {
            $response = $this->loadAttachmentsField($response);
        }
        if (in_array('automationsNewLead', $visibleFields)) {
            $response = $this->loadAutomationsNewLead($response);
        }
        if (in_array('automationsEmailSendStep', $visibleFields)) {
            $response = $this->loadAutomationsEmailSendStep($response);
        }
        if (in_array('automationsProposalResendRule', $visibleFields)) {
            $response = $this->loadAutomationsProposalResendRule($response);
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


    private function loadAttachmentsField(array $response): array
    {
        if (!$this->resource->relationLoaded('attachments')) {
            $this->resource->load('attachments');
        }
        $response['attachments'] = $this->resource->attachments;
        return $response;
    }


    private function loadAutomationsNewLead(array $response): array
    {
        // Eager loading not working so well for this relation with two keys.
        $response['automationsNewLead'] = $this->resource->automationsNewLead;
        return $response;
    }


    private function loadAutomationsEmailSendStep(array $response): array
    {
        if (!$this->resource->relationLoaded('automationsEmailSendStep')) {
            $this->resource->load('automationsEmailSendStep');
        }
        $response['automationsEmailSendStep'] = $this->resource->automationsEmailSendStep;
        return $response;
    }


    private function loadAutomationsProposalResendRule(array $response): array
    {
        if (!$this->resource->relationLoaded('automationsProposalResendRule')) {
            $this->resource->load('automationsProposalResendRule');
        }
        $response['automationsProposalResendRule'] = $this->resource->automationsProposalResendRule;
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
