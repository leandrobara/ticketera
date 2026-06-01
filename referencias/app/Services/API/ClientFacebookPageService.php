<?php

namespace App\Services\API;

use Exception;
use App\Helpers\RedisHelper;
use App\Helpers\FacebookAdHelper;
use Illuminate\Support\Collection;
use App\Models\ClientFacebookPage;
use App\Helpers\FacebookPageHelper;
use Illuminate\Support\Facades\Cache;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\ClientFacebookPageRepository;
use App\DTO\FacebookPage\ClientFacebookPageLeadInfoDTO;
use Facebook\Exceptions\FacebookAuthenticationException;
use App\Services\API\Dispatchers\FacebookLogDispatcherService;


class ClientFacebookPageService
{

    use GetClientFromRequest;

    private $redirectUrl;
    private $leadService;
    private $facebookPageHelper;
    private $facebookAdHelper;
    private $clientFacebookPageRepository;
    private $facebookLogDispatcherService;


    public function __construct(
        ClientFacebookPageRepository $clientFacebookPageRepository,
        FacebookPageHelper $facebookPageHelper,
        FacebookAdHelper $facebookAdHelper,
        LeadService $leadService,
        FacebookLogDispatcherService $facebookLogDispatcherService,
        string $redirectUrl
    ) {
        $this->leadService = $leadService;
        $this->redirectUrl = $redirectUrl;
        $this->facebookAdHelper = $facebookAdHelper;
        $this->facebookPageHelper = $facebookPageHelper;
        $this->facebookLogDispatcherService = $facebookLogDispatcherService;
        $this->clientFacebookPageRepository = $clientFacebookPageRepository;
    }


    public function findAllByClient()
    {
        $facebookPages = $this->clientFacebookPageRepository->findAllByClient($this->getClient());
        foreach ($facebookPages as $fbPageDBModel) {
            try {
                // if I can get the FB page info with the token stored in DB, the account is linked
                $facebookPageData = $this->facebookPageHelper->getFacebookSubscribedPageInfo(
                    $fbPageDBModel->page_id, $fbPageDBModel->page_token
                );
            } catch (Exception $e) {
                $fbPageDBModel->is_linked = false;
                continue;
            }

            $fbPageDBModelName = $fbPageDBModel->name;
            $fbPageDBModelAbout = $fbPageDBModel->about;
            $fbPageDataName = $facebookPageData['name'] ?? null;
            $fbPageDataAbout = $facebookPageData['about'] ?? null;
            //If name or about are empty, update them from Facebook data
            if (($fbPageDBModelName != $fbPageDataName) || ($fbPageDBModelAbout != $fbPageDataAbout)) {
                $this->clientFacebookPageRepository->updateNameAndAbout(
                    $fbPageDBModel, $fbPageDataName, $fbPageDataAbout
                );
            }
            $fbPageDBModel->is_linked = true;
        }
        return $facebookPages;
    }


    public function getFacebookSubscriptionUrl(): string
    {
        // cache client
        $subdomain = $this->getClient()->subdomain;
        Cache::store('redis')->set('FB_' . $subdomain, $this->getClient(), 3600);
        return $this->facebookPageHelper->getFacebookLoginUrl($subdomain);
    }


    public function subscribeFacebookPages(): Collection
    {
        $pages = $this->facebookPageHelper->getFacebookSubscribedPages();
        $clientFacebookPages = new Collection();
        foreach ($pages as $page) {
            // find facebook page
            $clientFacebookPage = $this
                ->clientFacebookPageRepository
                ->findWithTrashedByClientAndPageId($this->getClient(), $page['id'])
            ;
            // get page info (name and about)
            $info = $this->facebookPageHelper->getFacebookSubscribedPageInfo($page['id'], $page['access_token']);
            $data = [
                'page_id' => $page['id'],
                'name' => $info['name'] ?? null,
                'about' => $info['about'] ?? null,
                'page_token' => $page['access_token'],
                'client_id' => $this->getClient()->id,
                'user_token' => $page['user_token'] ?? null,
            ];

            // if exists fill and null delete_at
            if ($clientFacebookPage) {
                $clientFacebookPage->fill($data);
                $clientFacebookPage->deleted_at = null;
                $clientFacebookPage->save();
                $clientFacebookPage->fresh();
            } else {
                $clientFacebookPage = $this->clientFacebookPageRepository->insert($this->getClient(), $data);
            }
            $clientFacebookPages->add($clientFacebookPage);
        }
        return $clientFacebookPages;
    }


    public function unsubscribeFacebookPage(ClientFacebookPage $clientFacebookPage): ClientFacebookPage
    {
        try {
            $this->facebookPageHelper->unsubscribeFacebookPage($clientFacebookPage);
        } catch (FacebookAuthenticationException $e) {
            // No hago nada acá, sigo y borro el row de la base de datos. Solo reporto a Sentry.
            report($e);
        }
        return $this->clientFacebookPageRepository->delete($clientFacebookPage);
    }


    public function validateWebhook(array $data): string
    {
        $mode = $data['hub_mode'];
        $token = $data['hub_verify_token'];
        $challenge = $data['hub_challenge'];
        return $this->facebookPageHelper->validateWebhook($mode, $token, $challenge);
    }


    public function processFacebookWebhookLeadgen(Collection $facebookLeadGenDTOs): Collection
    {
        $fbLeadsDTOs = new Collection();
        foreach ($facebookLeadGenDTOs as $facebookLeadGenDTO) {
            if ($facebookLeadGenDTO->formId) {
                $clientFacebookPage = $this->clientFacebookPageRepository->findOneByPageId(
                    $facebookLeadGenDTO->pageId
                );
                if (!$clientFacebookPage) {
                    continue;
                }
                if (!$clientFacebookPage->client || !$clientFacebookPage->client->enabled) {
                    continue;
                }
                $fbLeadDataArr = $this->facebookAdHelper->getFacebookLeadDataFromLeadgenDTO(
                    $facebookLeadGenDTO, $clientFacebookPage
                );
                
                $fbFormDataArr = [];
                try {
                    $fbFormDataArr = $this->facebookAdHelper->getFacebookFormDataById(
                        $facebookLeadGenDTO->formId, $clientFacebookPage
                    );
                } catch (Exception $e) {
                    // report($e);
                }

                try {
                    $adsInfoArr = [];
                    if ($facebookLeadGenDTO->adId) {
                        $adsInfoArr = $this->facebookAdHelper->getCampaignAndAdsNamesByAdId(
                            $facebookLeadGenDTO->adId, $clientFacebookPage
                        );
                    }
                } catch (Exception $e) {
                    // report($e);
                }

                $fbLeadDTO = ClientFacebookPageLeadInfoDTO::build(
                    $clientFacebookPage, $fbLeadDataArr, $fbFormDataArr, $adsInfoArr
                );
                $fbLeadsDTOs->add($fbLeadDTO);
                $this->facebookLogDispatcherService->logLeadDataReceived(
                    $clientFacebookPage, $fbLeadDataArr, $fbFormDataArr
                );
            }
        }
        $newLeads = new Collection();
        foreach ($fbLeadsDTOs as $fbLeadDTO) {
            $newLead = $this->leadService->createFromFacebookLeadDTO($fbLeadDTO);
            $newLeads->push($newLead);
        }
        return $newLeads;
    }

}
