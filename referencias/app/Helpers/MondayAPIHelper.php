<?php

namespace App\Helpers;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;


// @TODO unificar con MondayAPIHelper2
// Esto es un wrapper que funciona vía Make.com
class MondayAPIHelper
{

    public function __construct()
    {
    }


    //
    // @DEPRECATED 29/04/2025, borrar cuando pueda
    //
    public function listChurnBoardItems(): array
    {
        // $endpoint = 'https://hook.us1.make.com/g1fwjpoyva19alokyr0va984rcnmilwu';
        $endpoint = 'https://hook.us1.make.com/p80ekscvku0ebcnx2io2w504duqrvwp2';
        $response = $this->makeApiCall($endpoint);
        return $response;
    }


    public function listOBBoardItems(array $opts = []): array
    {
        $limit = $opts['limit'] ?? 99999;
        // $endpoint = "https://hook.us1.make.com/xdbgo597k7u3xg8vevy9rts9k2sr29cp?limit={$limit}";
        $endpoint = "https://hook.us1.make.com/87vmww55phpbqf2ekp92cicgt8a2bln8?limit={$limit}";
        $response = $this->makeApiCall($endpoint, ['limit' => $limit]);
        return $response;
    }


    // @deprecated deprecado
    // public function updateOBBoardItemSituationStatus(
    //     int $boardItemId,
    //     int $lastWeekHitsCount,
    //     string $situationStatus,
    // ): array {
    //     $endpoint = 'https://hook.us1.make.com/w2oaod27wr8w1ycin02dyqs8tbd2e67f';
    //     $payload = [
    //         'board_item_id' => $boardItemId,
    //         'situationStatus' => $situationStatus,
    //         'lastWeekHitsCount' => $lastWeekHitsCount,
    //     ];

    //     $response = Http::post($endpoint, $payload);
    //     if (!$response->successful() || !$response->json()) {
    //         throw new Exception($response->body());
    //     }
        
    //     $responseArr = $response->json();
    //     $success = $responseArr['success'] ?? false;
    //     if (!$success) {
    //         throw new Exception($response->body());
    //     }
    //     return $response['data'];
    // }


    public function updateOBBoardItemOnboardingVariables(
        int $boardItemId,
        int $totalHitsFlagIsSuccess,
        int $manualLeadsFlagIsSuccess,
        int $statusChangesFlagIsSuccess,
        int $automaticLeadsFlagIsSuccess,
    ): bool {
        if (!$totalHitsFlagIsSuccess &&
            !$manualLeadsFlagIsSuccess &&
            !$statusChangesFlagIsSuccess &&
            !$automaticLeadsFlagIsSuccess
        ) {
            return false;
        }
        
        // $endpoint = 'https://hook.us1.make.com/7vp7289ybwsrq8ggp2q6pmlo4h2ergjo';
        $endpoint = 'https://hook.us1.make.com/3v5f8xpmmh7chjps6icz0zrknztj1f27';
        $payload = [
            'board_item_id' => $boardItemId,
            'totalHitsFlagIsSuccess' => $totalHitsFlagIsSuccess,
            'manualLeadsFlagIsSuccess' => $manualLeadsFlagIsSuccess,
            'statusChangesFlagIsSuccess' => $statusChangesFlagIsSuccess,
            'automaticLeadsFlagIsSuccess' => $automaticLeadsFlagIsSuccess,
        ];

        $response = Http::post($endpoint, $payload);
        if (!$response->successful()) {
            throw new Exception($response->body());
        }
        
        return $response->body() == 'Accepted';
    }


    private function makeApiCall(string $endpoint, array $params = [], ?string $pageToken = null): array
    {
        $query = $params;
        if ($pageToken) {
            $query['page_token'] = $pageToken;
        }
        $response = Http::timeout(120)->get($endpoint, $query);
        if ($response->successful()) {
            return $response->json();
        }
        throw new Exception('Error on API request: ' . $response->body());
    }

}
