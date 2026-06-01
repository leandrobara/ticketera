<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class UserResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        if (!$visibleFields) {
            $response = [
                'id' => $this->resource->id,
                'type' => $this->resource->type,
                'name' => $this->resource->name,
                'phone' => $this->resource->phone,
                'email' => $this->resource->email,
                'enabled' => $this->resource->enabled,
                'username' => $this->resource->username,
                'client_id' => $this->resource->client_id,
                'last_name' => $this->resource->last_name,
                'updated_at' => $this->resource->updated_at,
                'wapi_is_synced' => $this->resource->wapi_is_synced,
                'email_is_verified' => $this->resource->email_is_verified,
                'wapi_session_phone_number' => $this->resource->wapi_session_phone_number,
                'enabled_to_receive_leads' => (boolean) $this->resource->enabled_to_receive_leads,
                'wap_sender_session_phone_number' => $this->resource->wap_sender_session_phone_number,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }

        if (in_array('googleGmailAPIUserToken', $visibleFields)) {
            $response = $this->loadGoogleGmailAPIUserToken($response);
        }
        if (in_array('googlePeopleAPIUserToken', $visibleFields)) {
            $response = $this->loadGooglePeopleAPIUserToken($response);
        }
        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('automationsNewLeadWhereAssigned', $visibleFields)) {
            $response = $this->loadAutomationsNewLeadWhereAssigned($response);
        }
        $response = $this->filterVisibleFields($response);
        return $response;
    }

    private function loadClientField(array $response): array
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }
        $clientRd = new ClientResource($this->resource->client);
        $clientRd->setVisibleFields([
            'id',
            'name',
            'version',
            'subdomain',
            'country_code',
            'clientSettings',
        ]);
        $response['client'] = $clientRd;
        return $response;
    }


    private function loadGooglePeopleAPIUserToken(array $response): array
    {
        if (!$this->resource->relationLoaded('googlePeopleAPIUserToken')) {
            $this->resource->load('googlePeopleAPIUserToken');
        }
        $response['googlePeopleAPIUserToken'] = $this->resource->googlePeopleAPIUserToken;
        return $response;
    }


    private function loadAutomationsNewLeadWhereAssigned(array $response): array
    {
        // if (!$this->resource->relationLoaded('automationsNewLeadWhereAssigned')) {
        //     $this->resource->load('automationsNewLeadWhereAssigned');
        // }
        $response['automationsNewLeadWhereAssigned'] = $this->resource->automationsNewLeadWhereAssigned;
        return $response;
    }


    private function loadGoogleGmailAPIUserToken(array $response): array
    {
        if (!$this->resource->relationLoaded('googleGmailAPIUserToken')) {
            $this->resource->load('googleGmailAPIUserToken');
        }
        $response['googleGmailAPIUserToken'] = $this->resource->googleGmailAPIUserToken;
        return $response;
    }
    
}
