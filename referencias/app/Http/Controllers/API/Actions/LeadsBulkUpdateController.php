<?php

namespace App\Http\Controllers\API\Actions;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Actions\LeadsBulkUpdateService;
use App\Http\Requests\Actions\LeadsBulkUpdateRequest;
use App\Http\Requests\Actions\LeadsBulkUpdateListPreviewRequest;
use App\Http\Resources\Actions\LeadsBulkUpdate\LeadsBulkUpdatePreviewResourceCollection;


class LeadsBulkUpdateController extends BaseAPIController
{

    public function listLeadsPreview(LeadsBulkUpdateListPreviewRequest $req)
    {
        set_time_limit(180);
        ini_set('memory_limit', '1900M');
        ini_set('max_execution_time', 180);
        
        // Limpia cualquier buffer de salida previo
        while (ob_get_level()) {
            ob_end_clean();
        }

        $dtos = resolve(LeadsBulkUpdateService::class)->getLeadsPreviewList($req->file);
        $rs = new LeadsBulkUpdatePreviewResourceCollection($dtos);
        
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


    public function updateLeads(LeadsBulkUpdateRequest $req)
    {
        set_time_limit(120);
        ini_set('memory_limit', '1000M');
        ini_set('max_execution_time', 120);

        $updated = resolve(LeadsBulkUpdateService::class)->updateLeads($req->validatedDTOs());
        return $this->getSuccessResponse(true);
    }

}
