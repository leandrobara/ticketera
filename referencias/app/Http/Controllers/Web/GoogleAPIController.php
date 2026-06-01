<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Web\GooglePeopleAPIOAuthRedirectRequest;


class GoogleAPIController extends BaseController
{

    public function peopleOauthRedirectLanding(GooglePeopleAPIOAuthRedirectRequest $req)
    {
        // dd($req->toArray(), $req->getClientId(), $req->getClientSubdomain(), $req->getCode());
        $clientSubdomain = $req->getClientSubdomain();
        $redirectUrl = config('app.google_people_api.oauth_redirect_handle_url');
        $redirectUrl = str_replace('{subdomain}', $clientSubdomain, $redirectUrl);
        $redirectUrl .= "?code={$req->getCode()}&cid={$req->getClientId()}&uid={$req->getUserId()}";
        return redirect($redirectUrl);
    }


    public function gmailOauthRedirectLanding(GooglePeopleAPIOAuthRedirectRequest $req)
    {
        // dd($req->toArray(), $req->getClientId(), $req->getClientSubdomain(), $req->getCode());
        $clientSubdomain = $req->getClientSubdomain();
        $redirectUrl = config('app.google_gmail_api.oauth_redirect_handle_url');
        $redirectUrl = str_replace('{subdomain}', $clientSubdomain, $redirectUrl);
        $redirectUrl .= "?code={$req->getCode()}&cid={$req->getClientId()}&uid={$req->getUserId()}";
        return redirect($redirectUrl);
    }

}