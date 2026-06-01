<?php

namespace App\Http\Controllers\API\Views;

use App\Models\Email;
use App\Exports\MassiveEmailExport;
use App\Services\API\LeadContactEmailService;
use App\Services\API\GmailMessagesLogService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\ListGmailMessageLogRequest;
use App\Http\Requests\Views\ShowGmailEmailModalRequest;
use App\Http\Requests\Views\CountGmailMessageLogRequest;
use App\Http\Resources\Views\GmailEmail\GmailEmailModalResource;
use App\Http\Resources\Views\GmailEmail\GmailEmailListResourceCollection;


class GmailMessagesLogController extends BaseAPIController
{

    public function showGmailEmailModal(ShowGmailEmailModalRequest $req)
    {
        $gmailMessage = resolve(GmailMessagesLogService::class)->findOneByClientAndGmailId(
            $req->client, $req->getGmailId()
        );
        return $this->getSuccessResponse(new GmailEmailModalResource($gmailMessage));
    }


    public function list(ListGmailMessageLogRequest $req)
    {
        $emails = resolve(GmailMessagesLogService::class)->list($req->client, $req->validated());
        return $this->getSuccessResponse(new GmailEmailListResourceCollection($emails));
    }


    public function count(CountGmailMessageLogRequest $req)
    {
        $count = resolve(GmailMessagesLogService::class)->count($req->client, $req->validated());
        return $this->getSuccessResponse($count);
    }

}
