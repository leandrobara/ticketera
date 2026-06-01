<?php

namespace App\Http\Controllers\API\WhatsAppSenderExtension;

use Throwable;
use App\Helpers\SystemHelper;
use App\Models\WhatsAppSending;
use Illuminate\Support\Facades\Cache;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\WAPSenderService;
use App\Helpers\WhatsAppAttachmentHelper;
use App\Services\API\WhatsAppSendingService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\WhatsAppSendingResource;
use App\Services\API\WhatsAppSendingMessageService;
use App\Http\Resources\WhatsAppSendingMessageResource;
use App\Http\Requests\WhatsAppSenderExtension\DownloadAttachmentRequest;
use App\Http\Requests\WhatsAppSenderExtension\SetPusherSyncStatusRequest;
use App\Http\Requests\WhatsAppSenderExtension\PauseWhatsAppSendingRequest;
use App\Http\Requests\WhatsAppSenderExtension\ResumeWhatsAppSendingRequest;
use App\Http\Requests\WhatsAppSenderExtension\CancelWhatsAppSendingRequest;
use App\Http\Requests\WhatsAppSenderExtension\CreateWhatsAppSendingRequest;
use App\Http\Requests\WhatsAppSenderExtension\FinishWhatsAppSendingRequest;
use App\Http\Requests\WhatsAppSenderExtension\GetLastWhatsAppSendingRequest;
use App\Http\Requests\WhatsAppSenderExtension\HandlePusherMessageErrorRequest;
use App\Http\Requests\WhatsAppSenderExtension\GetCurrentWhatsAppSendingRequest;
use App\Http\Resources\WhatsAppSenderExtension\WhatsAppSenderPopUpInfoResource;
use App\Http\Requests\WhatsAppSenderExtension\HandlePusherMessageSuccessRequest;
use App\Http\Requests\WhatsAppSenderExtension\GetWhatsAppSenderPopUpInfoRequest;
use App\Http\Requests\WhatsAppSenderExtension\MarkWhatsAppSendingMessageAsSentRequest;
use App\Http\Requests\WhatsAppSenderExtension\HandlePusherChatMessagesResponseRequest;
use App\Http\Requests\WhatsAppSenderExtension\HandlePusherChatMessageMediaResponseRequest;
use App\Http\Requests\WhatsAppSenderExtension\MarkWhatsAppSendingMessageAsDispatchedRequest;
use App\Http\Requests\WhatsAppSenderExtension\UnmarkWhatsAppSendingMessageAsDispatchedRequest;


class WhatsAppSenderController extends BaseAPIController
{

    public function getPopUpInfo(GetWhatsAppSenderPopUpInfoRequest $req)
    {
        $dto = resolve(WhatsAppSendingService::class)->getWapSenderPopUpInfoDTO(
            $req->user, $req->whatsAppSenderAppVersion
        );
        $rs = new WhatsAppSenderPopUpInfoResource($dto);
        return $this->getSuccessResponse($rs);
    }


    public function getLastSending(GetLastWhatsAppSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->findLastByUserAndType(
            $req->user, WhatsAppSending::WAP_SENDER_TYPE
        );
        $rs = $whatsAppSending ? new WhatsAppSendingResource($whatsAppSending) : null;
        return $this->getSuccessResponse($rs);
    }


    public function getCurrentSending(GetCurrentWhatsAppSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->findCurrentSendingByUserAndType(
            $req->user, WhatsAppSending::WAP_SENDER_TYPE
        );
        $rs = $whatsAppSending ? new WhatsAppSendingResource($whatsAppSending) : null;
        return $this->getSuccessResponse($rs);
    }


    public function create(CreateWhatsAppSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->createNewWAPSenderSending($req->validatedDTO());
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function cancelCurrentSending(CancelWhatsAppSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->cancel($req->getCurrentSending());
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function pauseCurrentSending(PauseWhatsAppSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->pause(
            $req->getCurrentSending(), $req->getPauseReason()
        );
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function resumeCurrentSending(ResumeWhatsAppSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->resume($req->getCurrentSending());
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function finishCurrentSending(FinishWhatsAppSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->finish($req->getCurrentSending());
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function markMessageAsSent(
        WhatsAppSendingMessage $whatsAppSendingMessage,
        MarkWhatsAppSendingMessageAsSentRequest $req
    ) {
        $wapMsg = resolve(WhatsAppSendingService::class)->markMessageAsSent(
            $whatsAppSendingMessage, $req->getIsSuccess(), $req->getError()
        );
        return $this->getSuccessResponse(new WhatsAppSendingMessageResource($wapMsg));
    }


    public function markMessageAsDispatched(
        WhatsAppSendingMessage $whatsAppSendingMessage,
        MarkWhatsAppSendingMessageAsDispatchedRequest $req
    ) {
        $wapMsg = resolve(WhatsAppSendingMessageService::class)->markAsDispatched($whatsAppSendingMessage);
        return $this->getSuccessResponse(new WhatsAppSendingMessageResource($wapMsg));
    }


    public function unmarkMessageAsDispatched(
        WhatsAppSendingMessage $whatsAppSendingMessage,
        UnmarkWhatsAppSendingMessageAsDispatchedRequest $req
    ) {
        $wapMsg = resolve(WhatsAppSendingMessageService::class)->unmarkAsDispatched($whatsAppSendingMessage);
        return $this->getSuccessResponse(new WhatsAppSendingMessageResource($wapMsg));
    }



    // WAP SENDER JOBS

    // Gestión de respuesta de error al mandar un mensaje job con WapSender.
    public function handlePusherMessageError(HandlePusherMessageErrorRequest $req)
    {
        $data = ['success' => false, 'error' => $req->input('errorCode')];
        Cache::store('redis')->set($req->input('browserTrackingKey'), $data, 60);
        return $this->getSuccessResponse(true);
    }

    // Gestión de respuesta de success al mandar un mensaje job con WapSender.
    public function handlePusherMessageSuccess(HandlePusherMessageSuccessRequest $req)
    {
        $data = ['success' => true, 'data' => $req->validated()];
        Cache::store('redis')->set($req->input('browserTrackingKey'), $data, 60);
        return $this->getSuccessResponse(true);
    }


    public function handlePusherChatMessagesResponse(HandlePusherChatMessagesResponseRequest $req)
    {
        $params = $req->validated();
        $success = $params['error'] ? false : true;
        $data = ['success' => $success, 'data' => $req->validated()];

        $pusherMessagesListKey = resolve(WAPSenderService::class)->buildPusherMessagesListKey(
            $params['clientId'], $params['fromPhoneNumber'], $params['phoneNumber']
        );
        Cache::store('redis')->set($pusherMessagesListKey, $data, 60);
        return $this->getSuccessResponse(true);
    }


    public function handlePusherChatMessageMediaResponse(HandlePusherChatMessageMediaResponseRequest $req)
    {
        $params = $req->validated();
        $success = $params['error'] ? false : true;
        $data = ['success' => $success, 'data' => $req->validated()];

        $pusherMessageMediaKey = resolve(WAPSenderService::class)->buildPusherMessageMediaKey(
            $params['clientId'], $params['fromPhoneNumber'], $params['chatMessageId']
        );
        Cache::store('redis')->set($pusherMessageMediaKey, $data, 60);
        return $this->getSuccessResponse(true);
    }


    public function setSyncStatusFromPusherResponse(SetPusherSyncStatusRequest $req)
    {
        resolve(WAPSenderService::class)->setSyncStatusFromPusherResponse($req->validated());
        return $this->getSuccessResponse(true);
    }


    public function downloadAttachment(
        WhatsAppSendingMessage $wapSendingMsg,
        string $attachmentHash,
        DownloadAttachmentRequest $req,
    ) {
        $wapAttachment = $req->whatsAppAttachment;
        $rawData = resolve(WhatsAppAttachmentHelper::class)->getWhatsAppAttachmentFileRawData($wapAttachment);
        SystemHelper::setBinaryDownloadHeaders($wapAttachment->original_filename, $wapAttachment->size);
        echo($rawData);
    }


}
