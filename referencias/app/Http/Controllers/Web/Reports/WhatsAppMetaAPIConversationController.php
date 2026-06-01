<?php

namespace App\Http\Controllers\Web\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class WhatsAppMetaAPIConversationController extends BaseController
{

    public function conversationsList(Request $request)
    {
        return view('web.reports.whatsapp.conversations.list', []);
    }

}
