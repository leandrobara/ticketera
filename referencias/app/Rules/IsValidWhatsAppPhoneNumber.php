<?php

namespace App\Rules;

use App\Helpers\PhonesHelper;
use Illuminate\Contracts\Validation\Rule;


class IsValidWhatsAppPhoneNumber implements Rule
{

    private string $errorMessage = 'Field :attribute must be a valid whatsapp phone number';


    public function __construct()
    {
    }


    public function passes($attribute, $value)
    {
        $phonesHelper = resolve(PhonesHelper::class);
        $onlyNumbersPhone = $phonesHelper->getOnlyNumbers($value);
        if ($onlyNumbersPhone != $value) {
            $this->errorMessage = 'Field :attribute must contains only numbers';
            return false;
        }

        if (!$phonesHelper->phoneHasValidCountryPrefix($value)) {
            $this->errorMessage = 'Field :attribute must contain a valid country prefix';
            return false;
        }

        return true;
    }


    public function message()
    {
        return $this->errorMessage;
    }

}
