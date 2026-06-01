<?php

namespace App\Http\Requests\Import;

use App\Http\Requests\APIBaseRequest;


class ImportGmailMessagesRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }

    public function getRequestName(): string
    {
        return 'ImportGmailMessages';
    }

}
