<?php

namespace App\Http\Controllers\API\Actions;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Actions\LeadsBulkUploadService;
use App\Http\Requests\Actions\LeadsBulkUploadRequest;
use App\Http\Requests\Actions\LeadsBulkUploadListPreviewRequest;
use App\Http\Resources\Actions\LeadsBulkUpload\LeadsBulkUploadPreviewResourceCollection;


class LeadsBulkUploadController extends BaseAPIController
{

    public function listLeadsPreview(LeadsBulkUploadListPreviewRequest $req)
    {
        set_time_limit(180);
        ini_set('memory_limit', '1900M');
        ini_set('max_execution_time', 180);
        
        // Limpia cualquier buffer de salida previo
        while (ob_get_level()) {
            ob_end_clean();
        }

        $dtos = resolve(LeadsBulkUploadService::class)->getLeadsPreviewList($req->file);
        $rs = new LeadsBulkUploadPreviewResourceCollection($dtos);
        
        // Limpia memoria antes de responder
        unset($dtos);
        gc_collect_cycles();
        
        $response = $this->getSuccessResponse($rs);

        // Si usas PHP-FPM, termina la request limpiamente
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        return $response;
    }


    public function uploadLeads(LeadsBulkUploadRequest $req)
    {
        set_time_limit(300);
        ini_set('memory_limit', '1900M');
        ini_set('max_execution_time', 300);

        $response = resolve(LeadsBulkUploadService::class)->uploadLeads($req->validatedDTOs());
        return $this->getSuccessResponse([
            'importedLeadIds' => $response->get('importedLeadIds'),
            'existentLeadIds' => $response->get('existentLeadIds'),
        ]);
    }

}