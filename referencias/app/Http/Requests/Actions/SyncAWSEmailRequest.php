<?php

namespace App\Http\Requests\Actions;

use App\Models\User;
use App\Models\Attachment;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InEmailTemplateReturnFields;


class SyncAWSEmailRequest extends APIBaseRequest
{

    protected $attachments = [];


    public function rules()
    {
        return [
            'email_from_address' => ['required', 'email'],
            'email_from_name' => ['required', 'string', 'max:100'],
        ];
    }

}
