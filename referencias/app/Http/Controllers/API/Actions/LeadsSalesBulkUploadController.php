<?php

namespace App\Http\Controllers\API\Actions;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Actions\LeadsSalesBulkUploadService;
use App\Http\Requests\Actions\LeadsSalesBulkUploadRequest;
use App\Http\Requests\Actions\LeadsSalesBulkUploadListPreviewRequest;
use App\Http\Resources\Actions\LeadsSalesBulkUpload\LeadsSalesBulkUploadPreviewResourceCollection;


class LeadsSalesBulkUploadController extends BaseAPIController
{

    public function listLeadsSalesPreview(LeadsSalesBulkUploadListPreviewRequest $req)
    {
        set_time_limit(180);
        ini_set('memory_limit', '1900M');
        ini_set('max_execution_time', 180);

        $dtos = resolve(LeadsSalesBulkUploadService::class)->getLeadSalesPreviewList($req->file);
        $rs = new LeadsSalesBulkUploadPreviewResourceCollection($dtos);
        return $this->getSuccessResponse($rs);
    }


    public function uploadLeadsSales(LeadsSalesBulkUploadRequest $req)
    {
        set_time_limit(300);
        ini_set('memory_limit', '1900M');
        ini_set('max_execution_time', 300);

        $response = resolve(LeadsSalesBulkUploadService::class)->uploadLeadsSales($req->validatedDTOs());
        return $this->getSuccessResponse([
            'importedLeadsSalesCount' => $response->get('importedLeadsSales')->count(),
            'nonImportedLeadsSalesCount' => $response->get('nonImportedLeadsSales')->count(),
        ]);
    }

}
