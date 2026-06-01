<?php

namespace App\Http\Controllers\API\Views\ClientyConfigurations;

use App\Models\User;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Services\API\UserService;
use App\Services\API\ClientService;
use App\Services\API\LandingService;
use App\Services\API\AwsDkimService;
use App\Services\API\EventsLogService;
use App\Services\API\Views\EmailService;
use App\Http\Resources\UserResourceCollection;
use App\Services\API\GoogleAPIUserTokenService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\ClientResourceCollection;
use App\Http\Resources\LandingResourceCollection;
use App\Http\Resources\NotificationResourceCollection;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Http\Resources\Views\ClientModal\ClientSettingModalResource;
use App\Http\Requests\Views\ClientyConfigurations\ListClientRequest;
use App\Http\Requests\Views\ClientyConfigurations\ClientModalRequest;
use App\Http\Requests\Views\ClientyConfigurations\AwsDkimModalRequest;
use App\Http\Resources\ClientyConfigWhatsAppMetaAPIConnectionResource;
use App\Http\Requests\Views\ClientyConfigurations\ClientUserListRequest;
use App\Exports\Reports\ClientyConfigurations\ClientPricingReportExport;
use App\Http\Requests\Views\ClientyConfigurations\ClientLandingListRequest;
use App\Http\Requests\Views\ClientyConfigurations\ClientEmailQuotaInfoRequest;
use App\Http\Requests\Views\ClientyConfigurations\GetGoogleAPIUserTokenRequest;
use App\Http\Requests\Views\ClientyConfigurations\UsersLifecycleInfoModalRequest;
use App\Http\Requests\Views\ClientyConfigurations\ClientPricingExportInfoRequest;
use App\Http\Requests\Views\ClientyConfigurations\ClientNotificationListModalRequest;
use App\Http\Requests\Views\ClientyConfigurations\ClientEmailSendingMetricsModalRequest;



class ClientController extends BaseAPIController
{

    public function list(ListClientRequest $req)
    {
        SystemHelper::setMemoryLimitMB(500);
        $clientsPagination = resolve(ClientService::class)->list($req->validated());
        $rs = (new ClientResourceCollection($clientsPagination))->loadOptionsFromRequest($req);
        $rs->setVisibleFields($req->getVisibleFields());
        return $this->getSuccessResponse($rs);
    }


    public function clientPricingList(ListClientRequest $req)
    {
        SystemHelper::setMemoryLimitMB(500);
        $clients = resolve(ClientService::class)->list($req->validated());
        $rs = (new ClientResourceCollection($clients))->loadOptionsFromRequest($req);
        $rs->setVisibleFields([
            'id',
            'name',
            'contract_type',
            'clientSettings',
            'usersCount',
            'enabledUsersCount',
            'landingsCount'
        ]);
        return $this->getSuccessResponse($rs);
    }


    public function clientPricingExport(ClientPricingExportInfoRequest $req)
    {
        SystemHelper::setMemoryLimitMB(512);
        $clients = resolve(ClientService::class)->clientPricingListToExport($req->validated());
        return (new ClientPricingReportExport($clients))->download('reporte-clienty-pricing.xlsx');
    }


    public function modal(Client $requestedClient, ClientModalRequest $req)
    {
        return $this->getSuccessResponse(new ClientSettingModalResource($requestedClient));
    }


    public function emailSendingMetricsModal(Client $requestedClient, ClientEmailSendingMetricsModalRequest $req)
    {
        $metricsDTO = resolve(EmailService::class)->getClientyConfigClientModalMetricsInfo($requestedClient);
        return $this->getSuccessResponse($metricsDTO->toArray());
    }


    public function notificationsList(Client $requestedClient, ClientNotificationListModalRequest $req)
    {
        $notifications = $requestedClient->notifications()->orderByDesc('created_at')->limit($req->getLimit())->get();
        $rs = new NotificationResourceCollection($notifications);
        $rs->setVisibleFields([ 'id', 'lead', 'user', 'type', 'client_id', 'created_at', 'updated_at']);

        return $this->getSuccessResponse($rs);
    }


    public function emailQuotaInfo(Client $requestedClient, ClientEmailQuotaInfoRequest $req)
    {
        $emailQuotaDTO = resolve(EmailService::class)->getClientyConfigEmailQuotaInfoDTO($requestedClient);
        return $this->getSuccessResponse($emailQuotaDTO);
    }


    public function usersLifecycleInfoModal(Client $requestedClient, UsersLifecycleInfoModalRequest $req)
    {
        $clientUsers = resolve(UserService::class)->findAllByClient($requestedClient);
        $usersLifecycleEventLogs = resolve(EventsLogService::class)->findUsersLifecycleEventLogsByClient(
            $requestedClient, ['order' => 'created_date_desc']
        );
        $response = [
            'usersLifecycleEventLogs' => $usersLifecycleEventLogs,
            'currentUsersMetrics' => [
                'usersCount' => $clientUsers->count(),
                'enabledUsersCount' => $clientUsers->where('enabled', 1)->count(),
                'disabledUsersCount' => $clientUsers->where('enabled', 0)->count(),
            ],
        ];
        return $this->getSuccessResponse($response);
    }


    public function landingListByClient(Client $requestedClient, ClientLandingListRequest $req)
    {
        $landingUrls = resolve(LandingService::class)->findAllByClient($requestedClient);
        $rs = new LandingResourceCollection($landingUrls);
        $rs->setVisibleFields([ 'id', 'url']);

        return $this->getSuccessResponse($rs);
    }


    public function awsDkimModal(Client $requestedClient, AwsDkimModalRequest $req)
    {
        $dkimInfo = resolve(AwsDkimService::class)->getDkimAndSpfCompleteInfo($req->getDomain());
        return $this->getSuccessResponse($dkimInfo);
    }


    public function userList(Client $requestedClient, ClientUserListRequest $req)
    {
        $users = resolve(UserService::class)->findAllByClient($requestedClient);
        $rs = (new UserResourceCollection($users))->loadOptionsFromRequest($req);
        $rs->setVisibleFields([
            'id',
            'name',
            'enabled',
            'username',
            'last_name',
            'google_gmail_app_name',
            'wap_sender_retry_delay_days',
            'wap_sender_session_phone_number'
        ]);
        return $this->getSuccessResponse($rs);
    }


    public function whatsAppMetaAPIConnectionsList(Client $requestedClient, ClientUserListRequest $req)
    {
        $wapConnections = resolve(WhatsAppMetaAPIService::class)->findConnectionsByClient(
            $requestedClient, ['with' => ['user', 'wapBot', 'wapSalesAgentBot.user']]
        );
        $wapConnections = $wapConnections->map(function ($wapConn) {
            return new ClientyConfigWhatsAppMetaAPIConnectionResource($wapConn);
        });
        return $this->getSuccessResponse($wapConnections);
    }


    public function getGoogleGmailApiUserTokenByUser(User $requestedUser, GetGoogleAPIUserTokenRequest $req)
    {
        $googleAPIUserToken = resolve(GoogleAPIUserTokenService::class)->findGmailTokenByUser($requestedUser);
        return $this->getSuccessResponse($googleAPIUserToken);
    }


    public function wapBotShow(Client $requestedClient, \App\Models\WapBot $requestedWapBot)
    {
        return $this->getSuccessResponse($requestedWapBot);
    }

}
