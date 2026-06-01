<?php

namespace App\Http\Controllers\API;

use App\Models\Client;
use App\Helpers\SystemHelper;
use App\Helpers\ClientyMailerAPIHelper;
use App\Services\API\AttachmentService;
use App\Http\Resources\AttachmentResource;
use App\Http\Requests\SaveAttachmentRequest;
use App\Http\Requests\DownloadAttachmentByHashRequest;


class AttachmentController extends BaseAPIController
{

    public function save(SaveAttachmentRequest $req)
    {
        $attachment = resolve(AttachmentService::class)->save($req->validatedDTO());
        return $this->getSuccessResponse((new AttachmentResource($attachment))->loadOptionsFromRequest($req));
    }


    public function downloadByHash(DownloadAttachmentByHashRequest $req)
    {
        $attachment = $req->getAttachment();
        $rawData = resolve(ClientyMailerAPIHelper::class)->getAttachmentRawDataByHash($attachment->hash);
        SystemHelper::setBinaryDownloadHeaders($attachment->name, $attachment->size);
        echo($rawData);
    }

}
