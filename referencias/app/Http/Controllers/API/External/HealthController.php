<?php

namespace App\Http\Controllers\API\External;

use Illuminate\Http\Request;
use App\Services\API\HealthService;
use App\Http\Controllers\API\BaseAPIController;


class HealthController extends BaseAPIController
{

    // Checks if supervisor is up (only workers, only staging and prod)
    public function supervisor(Request $request, HealthService $healthService)
    {
        $isUp = $healthService->isSupervisorUp();
        return $this->getResponse($isUp);
    }


    public function redis(Request $request, HealthService $healthService)
    {
        $isUp = $healthService->isRedisUp();
        return $this->getResponse($isUp);
    }


    public function apache(Request $request, HealthService $healthService)
    {
        $isUp = $healthService->isApacheUp();
        return $this->getResponse($isUp);
    }


    public function elastic(Request $request, HealthService $healthService)
    {
        $isUp = $healthService->isElasticUp();
        return $this->getResponse($isUp);
    }


    public function database(Request $request, HealthService $healthService)
    {
        $isUp = $healthService->isDatabaseUp();
        return $this->getResponse($isUp);
    }


    protected function getResponse(bool $isUp)
    {
        $httpCode = $isUp ? 200 : 500;
        $response = $isUp ? $this->getSuccessResponse($isUp) : $this->getErrorResponse($isUp);
        return response()->json($response, $httpCode);
    }

}