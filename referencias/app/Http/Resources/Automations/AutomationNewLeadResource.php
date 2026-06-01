<?php

namespace App\Http\Resources\Automations;

use App\Models\User;
use Illuminate\Support\Collection;
use App\Http\Resources\ClientResource;
use App\Http\Resources\UserResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AutomationNewLeadResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('formFieldsToMatch', $visibleFields)) {
            $response = $this->loadFormFieldsToMatchField($response);
        }
        if (in_array('utmParametersToMatch', $visibleFields)) {
            $response = $this->loadUtmParametersToMatchField($response);
        }
        if (in_array('trackingParametersToMatch', $visibleFields)) {
            $response = $this->loadTrackingParametersToMatchField($response);
        }
        if (in_array('leadCustomFieldsMatch', $visibleFields)) {
            $response = $this->loadLeadCustomFieldsMatchField($response);
        }
        if (in_array('leadCustomFieldsMapping', $visibleFields)) {
            $response = $this->loadLeadCustomFieldsMapping($response);
        }
        if (in_array('triggeringLandings', $visibleFields)) {
            $response = $this->loadTriggeringLandingsField($response);
        }
        if (in_array('tagsToAdd', $visibleFields)) {
            $response = $this->loadTagsToAddField($response);
        }
        if (in_array('statusToAssign', $visibleFields)) {
            $response = $this->loadStatusToAssignField($response);
        }
        if (in_array('usersToAssign', $visibleFields)) {
            $response = $this->loadUsersToAssignField($response);
        }
        if (in_array('askPhoneEmailTemplate', $visibleFields)) {
            $response = $this->loadAskPhoneEmailTemplateField($response);
        }
        if (in_array('autoReplyEmailTemplate', $visibleFields)) {
            $response = $this->loadAutoReplyEmailTemplateField($response);
        }
        if (in_array('acquisitionChannelToAdd', $visibleFields)) {
            $response = $this->loadAcquisitionChannelField($response);
        }

        // $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadClientField(array $response): array
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }
        $visibleFields = ['id', 'name', 'subdomain', 'country_code', 'version'];
        $clientRs = new ClientResource($this->resource->client);
        $clientRs->setVisibleFields($visibleFields);
        $response['client'] = $clientRs;
        return $response;
    }


    private function loadFormFieldsToMatchField(array $response): array
    {
        if (!$this->resource->relationLoaded('formFieldsToMatch')) {
            $this->resource->load('formFieldsToMatch');
        }
        $response['formFieldsToMatch'] = $this->resource->formFieldsToMatch;
        return $response;
    }


    private function loadUtmParametersToMatchField(array $response): array
    {
        if (!$this->resource->relationLoaded('utmParametersToMatch')) {
            $this->resource->load('utmParametersToMatch');
        }
        $response['utmParametersToMatch'] = $this->resource->utmParametersToMatch;
        return $response;
    }


    private function loadTrackingParametersToMatchField(array $response): array
    {
        if (!$this->resource->relationLoaded('trackingParametersToMatch')) {
            $this->resource->load('trackingParametersToMatch');
        }
        $response['trackingParametersToMatch'] = $this->resource->trackingParametersToMatch;
        return $response;
    }


    private function loadLeadCustomFieldsMatchField(array $response): array
    {
        if (!$this->resource->relationLoaded('leadCustomFieldsMatch')) {
            $this->resource->load('leadCustomFieldsMatch');
        }
        if (!$this->resource->relationLoaded('leadCustomFieldsMatch.leadCustomField')) {
            $this->resource->load('leadCustomFieldsMatch.leadCustomField');
        }
        $response['leadCustomFieldsMatch'] = $this->resource->leadCustomFieldsMatch;
        return $response;
    }


    private function loadLeadCustomFieldsMapping(array $response): array
    {
        if (!$this->resource->relationLoaded('leadCustomFieldsMapping')) {
            $this->resource->load('leadCustomFieldsMapping');
        }
        if (!$this->resource->relationLoaded('leadCustomFieldsMapping.leadCustomField')) {
            $this->resource->load('leadCustomFieldsMapping.leadCustomField');
        }
        $response['leadCustomFieldsMapping'] = $this->resource->leadCustomFieldsMapping;
        return $response;
    }


    private function loadTriggeringLandingsField(array $response): array
    {
        $response['triggeringLandings'] = $this->resource->triggeringLandings;
        return $response;
    }


    private function loadTagsToAddField(array $response): array
    {
        $response['tagsToAdd'] = $this->resource->tagsToAdd;
        return $response;
    }


    private function loadStatusToAssignField(array $response): array
    {
        $response['statusToAssign'] = $this->resource->statusToAssign;
        return $response;
    }


    private function loadAcquisitionChannelField(array $response): array
    {
        $response['acquisitionChannelToAdd'] = $this->resource->acquisitionChannelToAdd;
        return $response;
    }


    private function loadAskPhoneEmailTemplateField(array $response): array
    {
        $response['askPhoneEmailTemplate'] = $this->resource->askPhoneEmailTemplate;
        return $response;
    }


    private function loadAutoReplyEmailTemplateField(array $response): array
    {
        $response['autoReplyEmailTemplate'] = $this->resource->autoReplyEmailTemplate;
        return $response;
    }


    private function loadUsersToAssignField(array $response): array
    {
        if (!$response['assign_user_ids']) {
            $response['usersToAssign'] = [];
            return $response;
        }

        $visibleFields = ['id', 'name', 'last_name', 'enabled_to_receive_leads', 'enabled'];
        $users = User::whereIn('id', $response['assign_user_ids'])->get();
        $rs = new UserResourceCollection($users);
        $rs->setVisibleFields($visibleFields);
        $response['usersToAssign'] = $rs;
        return $response;
    }

}
