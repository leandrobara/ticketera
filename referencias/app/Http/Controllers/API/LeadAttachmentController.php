<?php

namespace App\Http\Controllers\API;

use App\Models\Lead;
use App\Helpers\SystemHelper;
use App\Models\LeadAttachment;
use App\Helpers\LeadAttachmentHelper;
use App\Services\API\LeadAttachmentService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\StoreLeadAttachmentRequest;
use App\Http\Requests\DeleteLeadAttachmentRequest;
use App\Http\Requests\DownloadLeadAttachmentRequest;
use App\Http\Resources\Views\LeadAttachment\LeadAttachmentResource;



class LeadAttachmentController extends BaseAPIController
{

    public function store(Lead $lead, StoreLeadAttachmentRequest $req)
    {
        $leadAttachment = resolve(LeadAttachmentService::class)->findOrSaveByFile($req->lead, $req->getUploadedFile());
        return $this->getSuccessResponse(new LeadAttachmentResource($leadAttachment));
    }


    public function delete(Lead $lead, LeadAttachment $leadAttachment, DeleteLeadAttachmentRequest $request)
    {
        $leadAttachment = resolve(LeadAttachmentService::class)->delete($leadAttachment);
        return $this->getSuccessResponse(new LeadAttachmentResource($leadAttachment));
    }


    public function download(Lead $lead, LeadAttachment $leadAttachment, DownloadLeadAttachmentRequest $req)
    {
        $rawData = resolve(LeadAttachmentHelper::class)->getLeadAttachmentFileRawData($leadAttachment);
        SystemHelper::setBinaryDownloadHeaders($leadAttachment->original_filename, $leadAttachment->size);
        echo($rawData);
    }

}
