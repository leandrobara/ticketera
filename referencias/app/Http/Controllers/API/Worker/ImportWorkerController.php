<?php

namespace App\Http\Controllers\API\Worker;

use Throwable;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Services\API\UserService;
use App\Models\GoogleAPIUserToken;
use App\Services\API\Import\ImportLeadService;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\GoogleAPIUserTokenService;
use App\Services\API\Import\ImportClientService;
use App\Http\Requests\Import\ImportLeadsRequest;
use App\Http\Requests\Import\ImportClientsRequest;
use App\Services\API\Import\ImportGmailMessagesService;
use App\Http\Requests\Import\ImportGmailMessagesRequest;
use App\Exceptions\Services\GoogleAPI\NoClientMatchException;
use App\Exceptions\Services\GoogleAPI\TokenHasNoPermissionsException;


class ImportWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(2000);
    }


    public function importClients(ImportClientsRequest $req)
    {
        $lockKey = 'ImportWorkerController:importClients';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 30)) {
            die('locked');
        }

        $service = resolve(ImportClientService::class);
        $leadsClientId = $req->input('leads_client_id', null);
        $leadsClients = $service->getLeadsClients($leadsClientId);
        $leadsClients = $leadsClients->sortBy('name');
        foreach ($leadsClients as $leadsClient) {
            resolve(LockHelper::class)->getLockByName($lockKey, 30);

            echo '<hr>';
            $client = $service->importLeadsClient($leadsClient);
            $paramsToShow = [
                'id', 'name', 'subdomain', 'emails', 'country_code', 'contract_type', 'business_area', 'timezone'
            ];
            $clientParamsArr = collect($client->toArray())->only($paramsToShow)->toArray();
            var_dump($clientParamsArr);
            if ($client->wasCreated) {
                echo '<h3 style="color:green;">Cliente creado</h3>';
            }
            if ($client->wasUpdated) {
                echo '<h3 style="color:green;">Cliente actualizado</h3>';
            }
            SystemHelper::doFlush();
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }
    

    public function importLeads(ImportLeadsRequest $req)
    {
        $lockKey = 'ImportWorkerController:importLeads';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 30)) {
            die('locked');
        }

        $service = resolve(ImportLeadService::class);
        $leadsLeads = $service->getLeadsLeads($req->getRequestParams());
        foreach ($leadsLeads as $leadsLead) {
            $importedLeadDTO = $service->importLeadsLead($leadsLead);
            echo $importedLeadDTO . '<br>' . PHP_EOL;
            SystemHelper::doFlush();

            resolve(LockHelper::class)->getLockByName($lockKey, 30);
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function importGmailMessages(ImportGmailMessagesRequest $req)
    {
        $lockKey = 'ImportWorkerController:importGmailMessages';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 60)) {
            die('locked');
        }

        $users = resolve(UserService::class)->findWithGmailAPIEnabled();
        foreach ($users as $user) {
            try {
                var_dump(collect($user->toArray())->only(['id', 'client_id', 'name', 'email'])->toArray());
                $resultDtos = resolve(ImportGmailMessagesService::class)->importUserMessages($user);
                
                foreach ($resultDtos as $resultRow) {
                    $fieldsToShowArr = collect($resultRow['dto']->toArray())->only(
                        ['gmailId', 'subject', 'emailAddressTo', 'emailAddressFrom']
                    )->toArray();
                    $fieldsToShowArr['error'] = $resultRow['error'];
                    $fieldsToShowArr['success'] = $resultRow['success'];
                }
                
                echo '<hr>';
            } catch (TokenHasNoPermissionsException $e) {
                dump($e);
                report($e);
                // Si NO tiene permisos, le borro el token.
                resolve(GoogleAPIUserTokenService::class)->deleteByUserAndType(
                    $user, GoogleAPIUserToken::GMAIL_API_TYPE
                );
            } catch (NoClientMatchException $e) {
                // Si el cliente no matchea, no reporto la exception.
                // Esto es por que CS Clienty, tiene emails de otros clientes y eso genera errores.
                dump($e);
            } catch (Throwable $e) {
                dump($e);
                report($e);
            }

            SystemHelper::doFlush();
            resolve(LockHelper::class)->getLockByName($lockKey, 60);
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }
    
}
