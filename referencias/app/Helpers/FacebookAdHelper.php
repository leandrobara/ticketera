<?php

namespace App\Helpers;

use DateTime;
use Exception;
use App\Models\ClientFacebookPage;
use FacebookAds\Logger\CurlLogger;
use Illuminate\Support\Collection;
use FacebookAds\Api as FacebookAPI;
use FacebookAds\Object\Lead as FacebookLeadObject;
use App\DTO\FacebookPage\ClientFacebookPageLeadGenDTO;


class FacebookAdHelper
{

    public function __construct(string $appId, string $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
    }


    public function getFacebookLeadDataFromLeadgenDTO(
        ClientFacebookPageLeadGenDTO $clientFacebookPageLeadGenDTO,
        ClientFacebookPage $facebookPage
    ): array {
        $facebookAPI = FacebookAPI::init($this->appId, $this->appSecret, $facebookPage->page_token);
        $facebookAPI->setLogger(new CurlLogger());
        $fbLeadDataArr = (new FacebookLeadObject($clientFacebookPageLeadGenDTO->leadgenId))->getSelf()->exportAllData();
        return $fbLeadDataArr;
    }


    /**
     * "id" => "1603321806987973"
     * "name" => "Generac Form - Febrero - Loncin 7,2 KVA"
     * "status" => "ACTIVE"
     * "locale" => "es_ES"
     * "tracking_parameters" => [
     *   ["key" => "Canal", "value" => "Mkt"],
     *   ["key" => "Marca", "value" => "Generac"],
     *   ...
     * ]
     */
    public function getFacebookFormDataById(string $fbFormId, ClientFacebookPage $facebookPage): array
    {
        $facebookAPI = FacebookAPI::init($this->appId, $this->appSecret, $facebookPage->page_token);
        $facebookAPI->setLogger(new CurlLogger());
        $fields = http_build_query(['fields' => 'id,name,status,locale,tracking_parameters']);
        $response = $facebookAPI->call("/{$fbFormId}?{$fields}");
        $body = json_decode($response->getBody(), true);
        return $body ?: [];
    }


    /**
     * [
     *   "ad_id" => "120245068429350747",
     *   "ad_name" => "Nuevo anuncio de Clientes potenciales",
     *   "adset_id" => "120245068429330747",
     *   "adset_name" => "Nuevo conjunto de anuncios de Clientes potenciales",
     *   "campaign_id" => "120245068429340747",
     *   "campaign_name" => "Clientes potenciales",
     * ]
     */
    public function getCampaignAndAdsNamesByAdId(string $fbAdId, ClientFacebookPage $facebookPage): array
    {
        if (!$facebookPage->user_token) {
            return [];
        }
        $token = $facebookPage->user_token;
        $facebookAPI = FacebookAPI::init($this->appId, $this->appSecret, $token);
        $facebookAPI->setLogger(new CurlLogger());
        $fields = http_build_query(['fields' => 'id,name,adset{id,name,campaign{id,name}}']);
        $response = $facebookAPI->call("/{$fbAdId}?{$fields}");
        $body = json_decode($response->getBody(), true);
        if (!$body) {
            return [];
        }
        return [
            'ad_id' => $body['id'] ?? null,
            'ad_name' => $body['name'] ?? null,
            'adset_id' => $body['adset']['id'] ?? null,
            'adset_name' => $body['adset']['name'] ?? null,
            'campaign_id' => $body['adset']['campaign']['id'] ?? null,
            'campaign_name' => $body['adset']['campaign']['name'] ?? null,
        ];
    }


    public function debugToken(string $token): array
    {
        $appToken = "{$this->appId}|{$this->appSecret}";
        $url = "https://graph.facebook.com/v21.0/debug_token?" . http_build_query([
            'input_token' => $token,  // el token que querés inspeccionar
            'access_token' => $appToken,                   // app_id|app_secret
        ]);
        $raw = file_get_contents($url);
        $response = $raw ? json_decode($raw, true) : [];
        return $response['data'] ?? [];
    }


    public function getRecentLeads(
        ClientFacebookPage $facebookPage,
        array $opts = []
    ): Collection {
        $limit = $opts['limit'] ?? 10;
        $dateEnd = $opts['dateEnd'] ?? null;
        $dateStart = $opts['dateStart'] ?? null;
        $includeFbFormData = $opts['includeFbFormData'] ?? false;
        $enableCrashReporter = $opts['enableCrashReporter'] ?? true;

        $facebookAPI = FacebookAPI::init(
            $this->appId, $this->appSecret, $facebookPage->page_token, $enableCrashReporter
        );
        $facebookAPI->setLogger(new CurlLogger());

        // Get leadgen forms associated with the page
        $fields = http_build_query(['fields' => 'id,name,status,locale,tracking_parameters']);
        $response = $facebookAPI->call("/{$facebookPage->page_id}/leadgen_forms?{$fields}");
        $body = json_decode($response->getBody(), true);
        $fbForms = $body['data'];

        $allLeads = new Collection;
        foreach ($fbForms as $fbFormData) {
            $params = [
                'limit' => $limit,
                'fields' => 'created_time,id,ad_id,form_id,field_data'
            ];

            $filters = [];
            if ($dateStart instanceof DateTime) {
                $filters[] = [
                    'field' => 'time_created',
                    'operator' => 'GREATER_THAN',
                    'value' => $dateStart->getTimestamp(),
                ];
            }
            if ($dateEnd instanceof DateTime) {
                $filters[] = [
                    'field' => 'time_created',
                    'operator' => 'LESS_THAN',
                    'value' => $dateEnd->getTimestamp(),
                ];
            }
            if ($filters) {
                $params['filtering'] = json_encode($filters);
            }

            $formId = $fbFormData['id'];
            $queryString = http_build_query($params);
            $response = $facebookAPI->call("/{$formId}/leads?{$queryString}");
            $body = json_decode($response->getBody(), true);
            $leads = $body['data'];

            // Filtro por fechas acá también para mayor robustez
            $filteredLeads = $leads;
            if ($dateStart instanceof DateTime || $dateEnd instanceof DateTime) {
                $filteredLeads = array_filter($leads, function ($fbLead) use ($dateStart, $dateEnd) {
                    $createdTime = new DateTime($fbLead['created_time']);
                    if ($dateStart instanceof DateTime && $createdTime < $dateStart) {
                        return false;
                    }
                    if ($dateEnd instanceof DateTime && $createdTime > $dateEnd) {
                        return false;
                    }
                    return true;
                });
            }

            if ($includeFbFormData) {
                $filteredLeads = array_map(function ($fbLead) use ($fbFormData) {
                    $fbLead['fbFormData'] = $fbFormData;
                    return $fbLead;
                }, $filteredLeads);
            }

            $allLeads = $allLeads->merge($filteredLeads);
        }

        $sortedLeads = $allLeads->sortByDesc(function ($lead) {
            return strtotime($lead['created_time']);
        })->values();
        return $sortedLeads;
    }

}
