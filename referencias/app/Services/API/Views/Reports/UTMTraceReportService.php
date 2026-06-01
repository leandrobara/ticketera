<?php

namespace App\Services\API\Views\Reports;

use App\Services\API\Views\LeadService;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;


class UTMTraceReportService
{

    use GetClientFromRequest;

    private $leadService;


    public function __construct(LeadService $leadService)
    {
        $this->leadService = $leadService;
    }


    public function list(array $options = []): LengthAwarePaginator
    {
        $options['sort'] = 'date_desc';
        $options['filters']['only_utm_leads'] = true;
        return $this->leadService->list($options);
    }

}
