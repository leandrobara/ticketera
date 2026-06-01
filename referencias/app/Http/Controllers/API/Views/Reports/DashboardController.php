<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Views\Reports\DashboardService;
use App\Http\Requests\Views\Reports\DashboardMetricsRequest;


class DashboardController extends BaseAPIController
{

    public function leadMetrics(DashboardMetricsRequest $req)
    {
        $periodDTO = $req->getPeriodDTO();
        $metrics = resolve(DashboardService::class)->leadMetrics($periodDTO);
        // sleep(3);
        return $this->getSuccessResponse(['metrics' => $metrics, 'period' => $periodDTO->toArray()]);
    }

    
    public function leadSaleMetrics(DashboardMetricsRequest $req)
    {
        $periodDTO = $req->getPeriodDTO();
        $metrics = resolve(DashboardService::class)->leadSaleMetrics($periodDTO);
        return $this->getSuccessResponse(['metrics' => $metrics, 'period' => $periodDTO->toArray()]);
    }


    public function proposalInfoMetrics(DashboardMetricsRequest $req)
    {
        $periodDTO = $req->getPeriodDTO();
        $metrics = resolve(DashboardService::class)->proposalInfoMetrics($periodDTO);
        return $this->getSuccessResponse(['metrics' => $metrics, 'period' => $periodDTO->toArray()]);
    }

    
    public function automationMetrics(DashboardMetricsRequest $req)
    {
        $periodDTO = $req->getPeriodDTO();
        $metrics = resolve(DashboardService::class)->automationMetrics($periodDTO);
        return $this->getSuccessResponse(['metrics' => $metrics, 'period' => $periodDTO->toArray()]);
    }

    
    public function sendingMetrics(DashboardMetricsRequest $req)
    {
        $periodDTO = $req->getPeriodDTO();
        $metrics = resolve(DashboardService::class)->sendingMetrics($periodDTO);
        return $this->getSuccessResponse(['metrics' => $metrics, 'period' => $periodDTO->toArray()]);
    }


    // public function emailsMetrics(DashboardMetricsRequest $req)
    // {
    //     $data = resolve(DashboardService::class)->emailsMetrics($req->validated());
    //     sleep(random_int(2, 10));
    //     return $this->getSuccessResponse($data);
    // }
    // public function whatsAppSendingMetrics(DashboardMetricsRequest $req)
    // {
    //     $data = resolve(DashboardService::class)->whatsAppSendingMetrics($req->validated());
    //     sleep(random_int(2, 10));
    //     return $this->getSuccessResponse($data);
    // }

}
