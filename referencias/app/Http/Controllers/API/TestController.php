<?php

namespace App\Http\Controllers\API;

use DateTime;
use Exception;
use DateTimeZone;
use Pusher\Pusher;
use App\Models\Lead;
use App\Models\User;
use App\Models\Status;
use App\Models\Client;
use App\Models\LeadSale;
use App\Exports\LeadExport;
use App\Helpers\LockHelper;
use App\Helpers\WAPIHelper;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use App\Helpers\SystemHelper;
use App\Models\WhatsAppSending;
use App\Models\LeadContactEmail;
use App\Models\LeadContactPhone;
use App\Helpers\MondayAPIHelper;
use App\Models\MongoDB\EventLog;
use App\Helpers\GoogleAPIHelper;
use App\Helpers\FacebookAdHelper;
use App\Models\AutomationNewLead;
use App\Helpers\MondayAPIHelper2;
use App\Services\API\LeadService;
use App\Models\ClientFacebookPage;
use App\Helpers\CalendlyAPIHelper;
use App\Http\Requests\TestRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\GoogleAPIUserToken;
use App\Models\WhatsAppAttachment;
use App\Services\API\ClientService;
use App\Helpers\FacebookPageHelper;
use App\Helpers\EmailValidatorHelper;
use App\Helpers\IntegrationApiHelper;
use Illuminate\Support\Facades\Cache;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\EventsLogService;
use App\Helpers\MailsSoValidatorHelper;
use App\Services\API\FacebookLogService;
use App\Helpers\WhatsAppAttachmentHelper;
use App\Models\WhatsAppMetaAPIConnection;
use App\DTO\Monday\MondayNPSBoardItemDTO;
use App\Services\API\StatusCategoryService;
use App\Services\API\WhatsAppSendingService;
use App\Services\API\WhatsAppTemplateService;
use App\Models\MongoDB\MondayChurnBoardClient;
use App\Models\MongoDB\CalendlyScheduledEvent;
use App\Helpers\EmailListVerifyValidatorHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\DTO\Monday\MondayAPIClientsBoardItemDTO;
use App\DTO\Monday\MondayAPIChurnBoardClientDTO;
use App\Services\API\MondayChurnBoardClientService;
use App\Services\API\CalendlyScheduledEventService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Services\API\GoogleAPI\GoogleCommonAPIService;
use App\DTO\FacebookPage\ClientFacebookPageLeadInfoDTO;
use App\Services\API\Automations\AutomationNewLeadService;
use App\Services\API\Views\LeadService as ViewsLeadService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Jobs\IntegrationAPIEvents\SendNewLeadDataToWebhookJob;
use App\DTO\Monday\MondayAPICelulaOBBoardClientLastWeeksHitsDTO;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPIPopulatedConnectionDTO;
use App\Services\API\Dispatchers\GoogleContactsEventsDispatcherService;
use App\Services\API\Dispatchers\IntegrationAPIEventsDispatcherService;
use App\Services\API\Dispatchers\EmailValidationEventsDispatcherService;


class TestController extends BaseAPIController
{
    // Nota: Las sesiones ahora se guardan en sesión PHP (persisten entre requests)

    public function index(TestRequest $req)
    {
        SystemHelper::setVarDumpMaxDepth(10);
        $run = $_GET['run'] ?? null;
        if (!$run) {
            die('no_running_param');
        }

        exit;
        $metaHelper = resolve(WhatsAppMetaAPIHelper::class);
        $metaConn = WhatsAppMetaAPIConnection::find(80); // ff clienty notifs number

        $wabaIds = $metaHelper->debugToken($metaConn->access_token);
        dump($wabaIds);
        $wabaIds = $metaHelper->extractWabaIdsFromToken($metaConn->access_token);
        dd($wabaIds);

        // ecovatio
        // 1271108698300517 -> waba_id
        // 1134934879694556 -> phone_number_id
        // $metaConn = WhatsAppMetaAPIConnection::find(25); // ecovatio
        
        
        // $res = $metaHelper->getWABAInfoById($metaConn->waba_id, $metaConn->access_token);
        // dd($res);

        // $res = $metaHelper->getMessagingLimitByWabaId($metaConn->waba_id, $metaConn->access_token);
        // dump($res);
        $res = $metaHelper->getPhoneNumberInfoById($metaConn->phone_number_id, $metaConn->access_token);
        dump($res);

        $res = $metaHelper->isWabaSubscribedToWebhooks($metaConn->waba_id, $metaConn->access_token);
        dd($res);

        // // Integration test 3 (CTWA_CLID WhatsApp Meta)
        // $leadId = 14547202;
        // $lead = Lead::findOrFail($leadId);
        // $webhookUrl = 'https://hook.us1.make.com/pzb92ryl2fyp0gn37m3kaeurnaq4opcq';
        // // $integrationAPIDispatcher = resolve(IntegrationAPIEventsDispatcherService::class);
        // // $integrationAPIDispatcher->dispatchSendNewLeadDataToWebhookJob($newLead, $webhookUrl);

        // $ok = resolve(IntegrationApiHelper::class)->sendLeadDataToEndpoint($lead, 'new_lead', $webhookUrl);
        // dd($ok);


        // // Integration test 2 (FB Form)
        // $leadId = 14549677;
        // $lead = Lead::findOrFail($leadId);
        // $webhookUrl = 'https://hook.us1.make.com/dnuw26x7exw043aoqo4nut9ygfzd2uoy';
        // // $integrationAPIDispatcher = resolve(IntegrationAPIEventsDispatcherService::class);
        // // $integrationAPIDispatcher->dispatchSendNewLeadDataToWebhookJob($newLead, $webhookUrl);

        // $ok = resolve(IntegrationApiHelper::class)->sendLeadDataToEndpoint($lead, 'new_lead', $webhookUrl);
        // dd($ok);

        exit;
        
        $fbPage = ClientFacebookPage::find(434);

        $tokenInfo = resolve(FacebookAdHelper::class)->debugToken('xxxx');
        dd($tokenInfo);

        $tokenInfo = resolve(FacebookAdHelper::class)->debugToken('xxxxx');
        dump($tokenInfo);

        $formData = resolve(FacebookAdHelper::class)->getFacebookFormDataById('2993901557472718', $fbPage);
        dump($formData);

        $adData = resolve(FacebookAdHelper::class)->getCampaignAndAdsNamesByAdId('120245068429350747', $fbPage);
        dd($adData);



        $metaConn = WhatsAppMetaAPIConnection::find(80);
        $metaHelper = resolve(WhatsAppMetaAPIHelper::class);

        $res = $metaHelper->debugAccessToken($metaConn->access_token);
        dump($res);


        

        $opts = [
            'limit' => 15,
            'includeFbFormData' => true,
            'enableCrashReporter' => false,
            'dateEnd' => new DateTime('now'),
            'dateStart' => new DateTime('5 days ago'),
        ];
        $fbLeads = resolve(FacebookAdHelper::class)->getRecentLeads($fbPage, $opts);
        dd($fbLeads);

        

        $formData = resolve(FacebookAdHelper::class)->getFacebookFormDataById('1570670837523318', $fbPage);
        dump($formData);


        $fbPages = ClientFacebookPage::find([986, 966]);
        foreach ($fbPages as $fbPage) {
            try {
                $lala = resolve(FacebookAdHelper::class)->debugToken($fbPage);
                dump($lala);
                @ob_flush();
                @flush();
                usleep(100000); // 100ms para que el buffer se vacíe en tiempo real
            } catch (Exception $e) {
                echo  $fbPage->id . ' - ' . $e->getMessage() . ' - continue<hr>';
                @ob_flush();
                @flush();
                usleep(100000);
                continue;
            }
        }
        exit;

       


        // Loguear todo lo que venga en el request (GET, POST, etc) en laravel.log
        // Log incoming request in a more readable format
        // \Log::info(
        //     '[TestController@index] Incoming request data'
        // );
        // \Log::info(
        //     'Request input:',
        //     ['input' => json_encode(request()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]
        // );
        // \Log::info(
        //     'Request headers:',
        //     ['headers' => json_encode(request()->headers->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]
        // );

        exit;

        $config = config("filesystems.disks.whatsapp_conversations_files");

        $client = new \Aws\S3\S3Client([
            'region' => $config['region'],
            'version' => 'latest',
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);

        // Listar todos los buckets
        $buckets = $client->listBuckets();
        foreach ($buckets['Buckets'] as $bucket) {
            dump($bucket['Name']);
        }


        exit;

        $client = Client::find(2367);
        $attrs = [
            'client_id' => $client->id,
            'name' => 'Últimos detalles',
        ];
        $defaultStatusCategories = resolve(StatusCategoryService::class)->findAllByClient($client);
        dump($defaultStatusCategories);
        $status = Status::factory()->state($attrs)->newClientDefault($defaultStatusCategories)->make($attrs);
        dd($status);
        
        exit;
        $user = User::find(4539);
        // $user = User::find(4385);
        resolve(WhatsAppEventsDispatcherService::class)->dispatchWhatsAppMetaAPISyncUsersTemplatesJob(
            triggerUser: $user, triggerAction: 'userWabaSync'
        );
        dd('dispatchWhatsAppMetaAPISyncUsersTemplatesJob USER 4539');
        exit;


        $metaHelper = resolve(WhatsAppMetaAPIHelper::class);
        $res = $metaHelper->getMessageTemplates('xxxx', 'xxxxx');
        dump($res);
        dump($res->pluck('id')->implode(','));
        dump($res->pluck('name')->implode(','));

        exit;

        $metaConn = WhatsAppMetaAPIConnection::find(9);
        $metaHelper = resolve(WhatsAppMetaAPIHelper::class);

        $res = $metaHelper->debugAccessToken($metaConn->access_token);
        dump($res);
        $res = $metaHelper->getMessageTemplates($metaConn->waba_id, $metaConn->access_token);
        dump($res);
        $res = $metaHelper->getWABAInfoById($metaConn->waba_id, $metaConn->access_token);
        dump($res);
        $res = $metaHelper->getPhoneNumbers($metaConn->waba_id, $metaConn->access_token);
        dump($res);
        // $res = $metaHelper->getWebhooksSubscriptions($metaConn->waba_id, $metaConn->access_token);
        // dump($res);
        $res = $metaHelper->debugAccessToken($metaConn->access_token);
        dump($res);

        $res = $metaHelper->getPhoneNumberInfoById($metaConn->phone_number_id, $metaConn->access_token);
        dump($res);
        exit;

        $res = $metaHelper->registerNumber($metaConn->phone_number_id, $metaConn->access_token);
        dd($res);

        $res = $metaHelper->getPhoneNumbers('1068001545317619', 'xxx');
        dump($res);
        exit;
        // $res = $metaHelper->getPhoneNumbers('327078482726266', 'xxx');
        // dump($res);
        $res = $metaHelper->getWABAs('327078482726266', 'xxx');
        dump($res);
        $res = $metaHelper->getWabaOwnerBusiness('1068001545317619', 'xxx');
        dump($res);
        $res = $metaHelper->getWabaSharedWithBusinesses('1068001545317619', 'xxx');
        dump($res);
        $res = $metaHelper->debugToken('xxx');
        dd($res);

        $wapConn = WhatsAppMetaAPIConnection::find(2);
        $wabaId = $wapConn->waba_id;
        $accessToken = $wapConn->access_token;
        $metaPhoneNumberId = $wapConn->phone_number_id;

        
        $wapSendingMsg = WhatsAppSendingMessage::find(14568735);
        $metaResponse = resolve(WhatsAppMetaAPIService::class)->sendMessage($wapSendingMsg);
        dd($metaResponse);

        $subscriptions = $metaHelper->getWebhooksSubscriptions($wabaId, $accessToken);
        dump($subscriptions);

        $isSubscribed = $metaHelper->isWabaSubscribedToWebhooks($wabaId, $accessToken);
        dd($isSubscribed);

        $phoneInfo = $metaHelper->getPhoneNumberInfoById($metaPhoneNumberId, $accessToken);
        dd($phoneInfo);
        exit;


        exit;


        $pusher = resolve(Pusher::class);
        $dateStr = (new DateTime('now -3 hours'))->format('d/m H:i') . ' hs.';
        $ok = resolve(Pusher::class)->trigger('ClientyWAPSenderChannel', 'sendNewWAPMessage', [
            'userId' => 22,
            'clientId' => 2,
            'isProposal' => false,
            'wAutomationLogId' => 5000,
            'phoneNumber' => '5491159711575',
            'whatsAppSendingMessageId' => 7000,
            'browserTrackingKey' => 'BW_TK_KEY',
            'chatMessage' => "Mensaje de Prueba! {$dateStr}",
        ]);
        var_dump($ok);


        // $data = $metaHelper->getWABAs('439507657238610', $accessToken);
        // var_dump($data);
        // exit;

        // $data = $metaHelper->getPhoneNumbers('24185407154398411', $accessToken);
        // var_dump($data);
        // exit;

        // $data = $metaHelper->getPhoneNumberInfoById('653796814488322', $accessToken);
        // var_dump($data);
        // exit;

        // $data = $metaHelper->getMessageTemplates('24185407154398411', $accessToken);
        // var_dump($data);
        // exit;

        // $data = $metaHelper->getMessageTemplates('24185407154398411', $accessToken);
        // var_dump($data);
        // exit;

        // $data = $metaHelper->sendTemplateMessage(
        //     to: '541159711575',
        //     languageCode: 'es_AR',
        //     accessToken: $accessToken,
        //     phoneNumberId: '653796814488322',
        //     templateName: 'plantilla_de_prueba_1',
        //     parameters: [['name' => 'nombre', 'value' => 'facu']],
        // );
        // var_dump($data);

        exit;


        dd(1);
        SystemHelper::setMemoryLimitMB(600);

        $mondayClientsBoardItems = resolve(MondayAPIHelper2::class)
            ->listClientsBoardItems(['limit' => 9999])
            ->map(fn ($item) => new MondayAPIClientsBoardItemDTO($item))
        ;
        dump('mondayClientsBoardItems', $mondayClientsBoardItems);
        Cache::store('redis')->set('mondayClientsBoardItems', $mondayClientsBoardItems, 3600 * 23);
        exit;

        $mondayClientsBoardItems = Cache::store('redis')->get('mondayClientsBoardItems');
        foreach ($mondayClientsBoardItems as $mondayItemDTO) {
            dump($mondayItemDTO->businessName);
            dump($mondayItemDTO->contactName);
            dump($mondayItemDTO->contactEmail);
            dump($mondayItemDTO->contactPhone);
            dump($mondayItemDTO->clientyClientSubdomain);

            $leads = Lead::where('client_id', 2)->where('company', $mondayItemDTO->businessName)->get();
            
            $emailHash = LeadContactEmail::buildHash($mondayItemDTO->contactEmail);
            $leadContactEmails = LeadContactEmail::where('hash', $emailHash)->where('client_id', 2)->get();
            $leadsFromEmails = $leadContactEmails->map(fn ($lce) => $lce->lead);
            
            $phoneHash = LeadContactPhone::buildHash($mondayItemDTO->contactPhone);
            $leadContactPhones = LeadContactPhone::where('hash', $phoneHash)->where('client_id', 2)->get();
            $leadsFromPhones = $leadContactPhones->map(fn ($lcp) => $lcp->lead);

            dump('$leads', $leads);
            dump('$leadsFromEmails', $leadsFromEmails);
            dump('$leadsFromPhones', $leadsFromPhones);
            
            $client = Client::withTrashed()
                ->where('subdomain', 'like', '%' . $mondayItemDTO->clientyClientSubdomain . '%')
                ->first()
            ;
            if ($client) {
                $clientEmails = $client->emails;

                $emailHashes = array_map(function ($e) {
                    return LeadContactEmail::buildHash($e);
                });
                $leadContactEmails = LeadContactEmail::whereIn('hash', $emailHashes)->where('client_id', 2)->get();
                $leadsFromEmails = $leadContactEmails->map(fn ($lce) => $lce->lead);
                dump('CLIENT $leadsFromEmails', $leadsFromEmails);
            }


            exit;
        }

        dd('mondayClientsBoardItems', $mondayClientsBoardItems);

        dd(1);


        $googleApiHelper = resolve(GoogleAPIHelper::class);
        $googleApiToken = GoogleAPIUserToken::find(1337);
        $googleClient = $googleApiHelper->getInitializedClient($googleApiToken);
        // dd($googleClient->isAccessTokenExpired());
        // $refreshToken = $googleClient->getRefreshToken();

        resolve(GoogleContactsEventsDispatcherService::class)->dispatchSyncNewLeadWithGoogleContactsJob(
            Lead::find(10962923), 0
        );


        dd(1);


        SystemHelper::setManualFlush();

        $clients = Client::withTrashed()->get();
        $mondayClientsBoardItems = resolve(MondayAPIHelper2::class)
            ->listClientsBoardItems(['limit' => 2000])
            ->map(fn ($item) => new MondayAPIClientsBoardItemDTO($item))
        ;
        $mondayClientsBoardItems = $mondayClientsBoardItems->filter(fn ($item) => $item->churnDate);
        $mondayClientsBoardItems = $mondayClientsBoardItems->filter(fn ($item) => !$item->clientyClientUrl);

        foreach ($mondayClientsBoardItems as $mondayClientItem) {
            $name = $mondayClientItem->name ?? null;
            $email = $mondayClientItem->contactEmail ?? null;
            $nameSlug = $name ? Str::slug($name, '') : null;
            dump($name, $nameSlug, $email);
            SystemHelper::doFlush();

            $client = Client::withTrashed()->where('name', 'like', '%' . $name . '%')->first();
            if (!$client) {
                $client = Client::withTrashed()->where('name', 'like', '%' . $nameSlug . '%')->first();
            }
            if (!$client) {
                $client = Client::withTrashed()->where('subdomain', 'like', '%' . $nameSlug . '%')->first();
            }
            if (!$client) {
                $client = $clients->filter(function ($c) use ($email, $nameSlug) {
                    foreach ($c->emails as $clientEmail) {
                        if ($clientEmail == $email) {
                            return true;
                        }
                        if (Str::contains($clientEmail, $nameSlug)) {
                            return true;
                        }
                        return false;
                    }
                })->first();
            }
            
            if ($client) {
                dump('CLIENT FOUND');
                $clientyClientUrl = "https://{$client->subdomain}.clienty.co";
                resolve(MondayAPIHelper2::class)->updateSingleColumn(
                    columnType: 'link',
                    boardId: 6967792411,
                    columnId: 'enlace_mkmtdb0f',
                    itemId: $mondayClientItem->id,
                    value: ['url' => $clientyClientUrl, 'text' => $clientyClientUrl],
                );
                dump($mondayClientItem->name, $clientyClientUrl);
            }
        }


        dd(1);
        
    }

}
