<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class ClientyConfigurationsController extends BaseController
{

    public function newsList(Request $request)
    {
        return view('web.clienty-configurations.news.list', []);
    }


    public function clientList(Request $request)
    {
        return view('web.clienty-configurations.clients.list', []);
    }


    public function NPSPollList(Request $request)
    {
        return view('web.clienty-configurations.nps-poll.list', []);
    }


    public function emailTemplateList(Request $request)
    {
        return view('web.clienty-configurations.email-template.list', []);
    }


    public function whatsAppTemplateList(Request $request)
    {
        return view('web.clienty-configurations.whats-app-template.list', []);
    }


    public function clientPricingList(Request $request)
    {
        return view('web.clienty-configurations.clients-pricing.list', []);
    }


    public function clientUsageReportPage(Request $request)
    {
        return view('web.clienty-configurations.client-usage-report.page', []);
    }


    public function clientUsageAllClientsReportPage(Request $request)
    {
        return view('web.clienty-configurations.client-usage-all-clients-report.page', []);
    }


    public function customerTrackingJourneyPage(Request $request)
    {
        return view('web.clienty-configurations.customer-tracking-journey.page', []);
    }

}
