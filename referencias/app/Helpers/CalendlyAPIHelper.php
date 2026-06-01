<?php

namespace App\Helpers;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;


class CalendlyAPIHelper
{

    private string $baseUrl = 'https://api.calendly.com/';


    public function __construct(
        protected string $accessToken,
        protected string $organizationId
    ) {
    }


    public function listPaginatedScheduledEvents(array $params = [], ?string $pageToken = null): array
    {
        $endpoint = 'scheduled_events';

        $allResults = [];
        $queryParams = [
            'count' => $params['count'] ?? 100,
            'organization' => $this->organizationId,
            'sort' => $params['sort'] ?? 'start_time:desc',
        ];
        if ($params['date_end'] ?? null) {
            $queryParams['max_start_time'] = $params['date_end']->format('Y-m-d\TH:i:s.u\Z');
        }
        if ($params['date_start'] ?? null) {
            $queryParams['min_start_time'] = $params['date_start']->format('Y-m-d\TH:i:s.u\Z');
        }
        $response = $this->makeApiCall($endpoint, $queryParams, $pageToken);
        
        $response['scheduledEvents'] = $response['collection'];
        unset($response['collection']);
        return $response; // ['scheduledEvents' => [], 'pagination' => []]
    }


    public function listEventInvites(string $scheduledEventId, array $params = []): array
    {
        $endpoint = 'scheduled_events/' . $scheduledEventId . '/invitees';

        $allResults = [];
        $pageToken = null;
        $queryParams = [
            'count' => $params['count'] ?? 20,
            'sort' => $params['sort'] ?? 'created_at:desc',
        ];
        $response = $this->makeApiCall($endpoint, $queryParams);
        return $response['collection'];
    }


    public function getScheduledEvent(string $scheduledEventId): array
    {
        $endpoint = 'scheduled_events/' . $scheduledEventId;

        $allResults = [];
        $pageToken = null;
        $response = $this->makeApiCall($endpoint);
        return $response['resource'] ?? [];
    }


    private function makeApiCall(string $endpoint, array $params = [], ?string $pageToken = null): array
    {
        $url = $this->baseUrl . $endpoint;
        $query = $params;
        if ($pageToken) {
            $query['page_token'] = $pageToken;
        }
        $response = Http::withToken($this->accessToken)->get($url, $query);
        if ($response->successful()) {
            return $response->json();
        }
        throw new Exception('Error on Calendly API request: ' . $response->body());
    }

}