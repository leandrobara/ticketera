<?php

namespace App\Http\Controllers\API\Actions\ClientyConfigurations;

use App\Models\Client;
use App\Services\API\ClientService;
use App\Services\API\AwsDkimService;
use App\Http\Resources\ClientResource;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Notifications\NotificationService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\Http\Requests\Actions\ClientyConfigurations\SyncClientWABATemplatesRequest;
use App\Http\Requests\Actions\ClientyConfigurations\ManagementClientAwsDkimRequest;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateClientWithSettingsRequest;
use App\Http\Requests\Actions\ClientyConfigurations\DeleteAllClientNotificationsRequest;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateValidAndSubscribedStatusEmailRequest;


class ClientController extends BaseAPIController
{

    public function updateWithSettings(Client $requestedClient, UpdateClientWithSettingsRequest $req)
    {
        $client = resolve(ClientService::class)->updateWithSettings(
            $requestedClient, $req->validatedClientData(), $req->validatedClientSettingsData()
        );
        return $this->getSuccessResponse((new ClientResource($client))->loadOptionsFromRequest($req));
    }


    public function deleteAllNotifications(Client $requestedClient, DeleteAllClientNotificationsRequest $req)
    {
        $opts = ['deletedBySystem' => false];
        $notifications = $requestedClient->notifications ?? collect([]);
        $deletedNotifications = resolve(NotificationService::class)->deleteNotifications($notifications, $opts);
        return $this->getSuccessResponse([]);
    }


    public function ensureAwsDkimIntegrity(Client $requestedClient, ManagementClientAwsDkimRequest $req)
    {
        $domain = $req->getDomain();
        $response = resolve(AwsDkimService::class)->ensureAwsDkimIntegrity($domain);
        return $this->getSuccessResponse($response);
    }


    public function syncClientWABATemplates(Client $requestedClient, SyncClientWABATemplatesRequest $req)
    {
        $users = $requestedClient->whatsAppMetaAPIConnections()
            ->select('waba_id', 'user_id')
            ->groupBy('waba_id')
            ->with('user')
            ->get()
            ->map(fn ($conn) => $conn->user)
            ->values()
        ;
        foreach ($users as $user) {
            resolve(WhatsAppEventsDispatcherService::class)->dispatchWhatsAppMetaAPISyncUsersTemplatesJob(
                triggerUser: $user, triggerAction: 'userWabaSync'
            );
        }
        return $this->getSuccessResponse(['userIds' => $users->pluck('id')]);
    }

}
