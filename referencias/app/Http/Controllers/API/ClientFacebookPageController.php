<?php

namespace App\Http\Controllers\API;

use Throwable;
use App\Models\ClientFacebookPage;
use App\Services\API\ClientFacebookPageService;
use App\Http\Requests\FacebookPage\SubscribeFacebookPageRequest;
use App\Http\Requests\FacebookPage\UnsubscribeFacebookPageRequest;
use App\Http\Resources\FacebookPage\ClientFacebookPageItemResource;
use App\Http\Requests\FacebookPage\ClientFacebookPageWebhookRequest;
use App\Http\Requests\FacebookPage\ValidatedFacebookPageWebhookRequest;
use App\Http\Resources\FacebookPage\ClientFacebookPageResourceCollection;


class ClientFacebookPageController extends BaseAPIController
{

    public function list(): array
    {
        $clientFacebookPages = resolve(ClientFacebookPageService::class)->findAllByClient();
        return $this->getSuccessResponse(
            new ClientFacebookPageResourceCollection($clientFacebookPages)
        );
    }


    // Devuelve la URL para vincular con FB.
    public function getSubscribeUrl(): array
    {
        $url = resolve(ClientFacebookPageService::class)->getFacebookSubscriptionUrl();
        return $this->getSuccessResponse($url);
    }


    public function subscribeFacebookPages(SubscribeFacebookPageRequest $request)
    {
        // subscribe page (Info is taken from cache)
        resolve(ClientFacebookPageService::class)->subscribeFacebookPages();
        // build the url to redirect the user
        $redirectUrl = config('app.facebook.redirect_url');

        $client = $request->client;
        $redirectUrl = str_replace('{subdomain}', $client->subdomain, $redirectUrl);

        return redirect($redirectUrl);
    }


    public function unsubscribeFacebookPage(
        ClientFacebookPage $clientFacebookPage,
        UnsubscribeFacebookPageRequest $request
    ): array {
        $clientFacebookPage = resolve(ClientFacebookPageService::class)->unsubscribeFacebookPage($clientFacebookPage);
        return $this->getSuccessResponse(
            (new ClientFacebookPageItemResource($clientFacebookPage))->loadOptionsFromRequest($request)
        );
    }


    public function webhook(ClientFacebookPageWebhookRequest $request)
    {
        resolve(ClientFacebookPageService::class)->processFacebookWebhookLeadgen($request->validatedDTO());
        return response([], 200);
    }


    public function validateWebhook(ValidatedFacebookPageWebhookRequest $request)
    {
        return resolve(ClientFacebookPageService::class)->validateWebhook($request->validated());
    }

}
