<?php

namespace App\Http\Controllers\Web\FAQ;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as BaseController;


class FAQController extends BaseController
{

    public function show(Request $request)
    {
        return view('web.faq.show', []);
    }
}