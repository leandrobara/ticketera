<?php

namespace App\Http\Controllers\API\Actions\ClientyConfigurations;

use App\Models\Client;
use App\Services\API\ClientService;
use App\Http\Resources\ClientResource;
use App\Services\API\LeadContactEmailService;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Notifications\NotificationService;
use App\Http\Resources\LeadContactEmailResourceCollection;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateClientWithSettingsRequest;
use App\Http\Requests\Actions\ClientyConfigurations\DeleteAllClientNotificationsRequest;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateLeadContactEmailValidAndSubscribedStatusRequest;


class LeadContactEmailController extends BaseAPIController
{

    public function updateValidAndSubscribedStatusByEmail(
        Client $requestedClient,
        UpdateLeadContactEmailValidAndSubscribedStatusRequest $req
    ) {
        $service = resolve(LeadContactEmailService::class);
        $updatedCount = $service->updateMultipleValidAndSubscribedStatus($requestedClient, $req->getEmails());
        return $this->getSuccessResponse(['updatedCount' => $updatedCount]);
    }

}
