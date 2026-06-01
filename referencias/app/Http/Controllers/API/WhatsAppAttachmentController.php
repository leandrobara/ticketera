<?php

namespace App\Http\Controllers\API;

use App\Helpers\SystemHelper;
use App\Models\WhatsAppAttachment;
use App\Helpers\WhatsAppAttachmentHelper;
use App\Services\API\WhatsAppAttachmentService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\StoreWhatsAppAttachmentRequest;
use App\Http\Requests\DeleteWhatsAppAttachmentRequest;
use App\Http\Requests\DownloadWhatsAppAttachmentRequest;
use App\Http\Resources\Views\WhatsAppAttachment\WhatsAppAttachmentResource;



class WhatsAppAttachmentController extends BaseAPIController
{

    public function store(StoreWhatsAppAttachmentRequest $req)
    {
        $whatsAppAttachment = resolve(WhatsAppAttachmentService::class)->findOrSaveByFile($req->getUploadedFile());
        return $this->getSuccessResponse(new WhatsAppAttachmentResource($whatsAppAttachment));
    }


    public function delete(WhatsAppAttachment $whatsAppAttachment, DeleteWhatsAppAttachmentRequest $request)
    {
        $whatsAppAttachment = resolve(WhatsAppAttachmentService::class)->delete($whatsAppAttachment);
        return $this->getSuccessResponse(new WhatsAppAttachmentResource($whatsAppAttachment));
    }


    public function download(WhatsAppAttachment $whatsAppAttachment, DownloadWhatsAppAttachmentRequest $req)
    {
        $rawData = resolve(WhatsAppAttachmentHelper::class)->getWhatsAppAttachmentFileRawData($whatsAppAttachment);
        SystemHelper::setBinaryDownloadHeaders($whatsAppAttachment->original_filename, $whatsAppAttachment->size);
        echo($rawData);
    }

}
