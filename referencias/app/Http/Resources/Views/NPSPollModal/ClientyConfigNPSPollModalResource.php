<?php

namespace App\Http\Resources\Views\NPSPollModal;

use App\Models\Client;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class ClientyConfigNPSPollModalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->toArray();
        $response = $this->loadHasAnswers($response);
        $response = $this->loadClientIds($response);
        return $response;
    }


    private function loadClientIds(array $response): array
    {
        if (!$this->resource->relationLoaded('NPSPollAnswers')) {
            $this->resource->load('NPSPollAnswers');
        }
        $NPSPollAnswers = $this->resource->NPSPollAnswers;
        $response['client_id'] = $NPSPollAnswers->pluck('client_id')->unique()->toArray();
        return $response;
    }


    private function loadHasAnswers(array $response): array
    {
        if (!$this->resource->relationLoaded('NPSPollAnswers')) {
            $this->resource->load('NPSPollAnswers');
        }
        $response['has_answers'] = $this->resource->NPSPollAnswers->count() > 0;
        return $response;
    }

}
