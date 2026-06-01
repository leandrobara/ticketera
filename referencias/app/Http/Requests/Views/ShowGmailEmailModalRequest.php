<?php

namespace App\Http\Requests\Views;

use App\Models\Email;
use App\Http\Requests\APIBaseRequest;


class ShowGmailEmailModalRequest extends APIBaseRequest
{

    private $leads = null;


    public function rules()
    {
        return [];
    }


    public function getGmailId(): string
    {
        return request()->gmailId;
    }

}
