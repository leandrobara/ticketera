<?php

namespace App\Http\Requests\Web;

use App\Helpers\GoogleAPIHelper;
use Illuminate\Foundation\Http\FormRequest;


class GooglePeopleAPIOAuthRedirectRequest extends FormRequest
{

    public function rules()
    {
        return [
            'scope' => ['required', 'string'],
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ];
    }


    public function getClientId(): int
    {
        return unserialize(parent::validated()['state'])['cid'];
    }


    public function getUserId(): int
    {
        return unserialize(parent::validated()['state'])['uid'];
    }


    public function getClientSubdomain(): string
    {
        return unserialize(parent::validated()['state'])['subdomain'];
    }


    public function getCode(): string
    {
        return parent::validated()['code'];
    }

}
