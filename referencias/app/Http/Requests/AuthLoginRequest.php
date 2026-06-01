<?php

namespace App\Http\Requests;

use DateTime;
use DateTimeZone;
use App\Exceptions\ValidationException;


class AuthLoginRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'username' => ['string', 'required'],
            'password' => ['string', 'required'],
            'remember_me' => ['sometimes', 'boolean'],
        ];
    }


    public function validatedAttributes()
    {
        $validated = parent::validated();

        $expirationMinutes = intval(config('auth.token_remember_me_expiration_minutes'));
        if (empty($validated['remember_me'])) {
            $expirationMinutes = intval(config('auth.token_expiration_minutes'));
        }

        $validated['expiration_date'] = new DateTime("now + $expirationMinutes minutes");
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        
        $dateNow = new DateTime('now');
        $dateNowTs = $dateNow->getTimestamp();
        $expirationDateTs = $validated['expiration_date']->getTimestamp();
        $diffMinutes = ($expirationDateTs - $dateNowTs) / 60;
        $validated['minutes_to_expire'] = intval(floor($diffMinutes));
        return $validated;
    }

}
