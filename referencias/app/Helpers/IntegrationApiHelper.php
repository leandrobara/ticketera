<?php

namespace App\Helpers;

use Exception;
use App\Models\Lead;
use App\Models\Task;
use App\Exceptions\HttpException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Integration\WebhookLeadResource;
use App\Http\Resources\Integration\WebhookTaskResource;


class IntegrationApiHelper
{

    public function sendLeadDataToEndpoint(Lead $lead, string $triggerCode, string $endpoint): string
    {
        $resource = new WebhookLeadResource($lead, $triggerCode);
        $rawBody = json_encode(['data' => $resource->toArray()]);
        try {
            $request = Http::withBody($rawBody, 'application/json');
            $response = $request->withOptions(['verify' => false])->post($endpoint)->body();
        } catch (Exception $e) {
            throw new HttpException($e->getMessage());
        }
        return $response;
    }


    public function sendTaskDataToEndpoint(Task $task, string $triggerCode, string $endpoint): string
    {
        $resource = new WebhookTaskResource($task, $triggerCode);
        $rawBody = json_encode(['data' => $resource->toArray()]);
        try {
            $request = Http::withBody($rawBody, 'application/json');
            $response = $request->withOptions(['verify' => false])->post($endpoint)->body();
        } catch (Exception $e) {
            throw new HttpException($e->getMessage());
        }
        return $response;
    }

}
