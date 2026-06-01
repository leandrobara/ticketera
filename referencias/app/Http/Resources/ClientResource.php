<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class ClientResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // Este chino con if se hace por un tema de performance!
        // Cargar clientSettings es costosisimo a nivel tiempo de render.
        $response = [];
        $visibleFields = $this->getFieldsToShow();
        if ($visibleFields) {
            foreach ($visibleFields as $fieldName) {
                $response[$fieldName] = $this->resource?->$fieldName;
            }
        } else {
            $response = $this->resource->attributesToArray();
        }

        if (in_array('manager', $visibleFields)) {
            $response = $this->loadManagerField($response);
        }
        if (in_array('clientSettings', $visibleFields)) {
            $response = $this->loadClientSettings($response);
        }
        if (in_array('usersCount', $visibleFields)) {
            $response = $this->loadUsersCount($response);
        }
        if (in_array('enabledUsersCount', $visibleFields)) {
            $response = $this->loadEnabledUsersCount($response);
        }
        if (in_array('landingsCount', $visibleFields)) {
            $response = $this->loadLandingsCount($response);
        }
        $response = $this->filterVisibleFields($response);
        return $response;
    }


    public function loadLandingsCount(array $response): array
    {
        if (!$this->resource->relationLoaded('landings')) {
            $this->resource->load('landings');
        }
        $response['landingsCount'] = $this->resource->landings->count();
        return $response;
    }


    public function loadManagerField(array $response): array
    {
        if (!$this->resource->relationLoaded('manager')) {
            $this->resource->load('manager');
        }
        $response['manager'] = $this->resource->manager;
        return $response;
    }
    

    public function loadClientSettings(array $response): array
    {
        if (!$this->resource->relationLoaded('clientSettings')) {
            $this->resource->load('clientSettings');
        }
        $response['clientSettings'] = $this->resource->clientSettings;
        return $response;
    }


    public function loadUsersCount(array $response): array
    {
        if (!$this->resource->relationLoaded('users')) {
            $this->resource->load('users');
        }
        $response['usersCount'] = $this->resource->users->count();
        return $response;
    }


    public function loadEnabledUsersCount(array $response): array
    {
        if (!$this->resource->relationLoaded('enabledUsers')) {
            $this->resource->load('enabledUsers');
        }
        $response['enabledUsersCount'] = $this->resource->enabledUsers->count();
        return $response;
    }

}
