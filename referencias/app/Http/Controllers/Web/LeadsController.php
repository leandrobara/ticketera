<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Services\Traits\GetRealIP;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Http;
use Illuminate\Cookie\CookieValuePrefix;
use App\Http\Controllers\Controller as BaseController;


class LeadsController extends BaseController
{
    use GetRealIP;

    public function showListPage(Request $request)
    {
        return view('web.leads.list', []);
    }


    public function showExportPage(Request $request)
    {
        return view('web.leads.export', []);
    }


    public function leadsExportFileDownload(Request $request)
    {
        $response = $this->getAPIResponse($request, '/api/views/lead/list/export');
        $headers = ['Content-Type' => 'application/vnd.ms-excel'];
        return response()->streamDownload(function () use ($response) {
            echo $response->body();
        }, 'clienty-prospectos.xlsx');
    }


    public function leadsExportByIdsFileDownload(Request $request)
    {
        $response = $this->getAPIResponse($request, '/api/views/lead/list/export-by-ids', 'post');
        $headers = ['Content-Type' => 'application/vnd.ms-excel'];
        return response()->streamDownload(function () use ($response) {
            echo $response->body();
        }, 'clienty-prospectos.xlsx');
    }


    protected function getAPIResponse(Request $request, string $uri, string $method = 'get')
    {
        $params = json_decode($request->input('params'), true);
        $params['userIp'] = $this->getIp();

        $bearerToken = $request->input('t');
        $endpoint = 'https://' . $request->getHost();
        
        $cookieName = config('auth.remember_token_cookie_name');
        $cookieVal = $request->cookie($cookieName);

        $encrypter = resolve(Encrypter::class);
        $cookieEncryptedVal = $encrypter->encrypt(
            CookieValuePrefix::create($cookieName, $encrypter->getKey()) . $cookieVal, false
        );

        $options = ['verify' => false];
        $cookies =  [$cookieName => $cookieEncryptedVal];
        $httpReq = Http::withOptions($options)
            ->withCookies($cookies, $request->getHost())
            ->withToken($bearerToken)
            ->timeout(300)
        ;
        $response = $httpReq->$method($endpoint . $uri, $params);
        return $response;
    }

}