<?php

namespace App\Helpers;

use App\Models\Client;
use Illuminate\Support\Str;
use App\Models\ClientSettings;
use App\Models\LeadContactPhone;


class PhonesHelper
{

    // Variante de formatPhoneForWhatsApp que respeta el setting enable_whatsapp_auto_phone_formatting del cliente.
    // Default (true/null): comportamiento histórico de formatPhoneForWhatsApp.
    public function formatPhoneForWhatsAppWithSettings(
        string $phone,
        ?string $countryCode = null,
        ?ClientSettings $clientSettings = null
    ): string {
        if ($clientSettings?->enable_whatsapp_auto_phone_formatting === false) {
            return Str::limit($this->getOnlyNumbers($phone), 18, '');
        }
        return $this->formatPhoneForWhatsApp($phone, $countryCode);
    }


    public function formatPhoneForWhatsApp(
        string $phone,
        ?string $countryCode = null
    ): string {
        $onlyNumbersPhone = $this->getOnlyNumbers($phone);
        $hasValidCountryPrefix = $this->phoneHasValidCountryPrefix($onlyNumbersPhone);

        // Fix Brasil -> el 1 es código de área en varias regiones
        if ($countryCode && $this->isBrasilCountryCode($countryCode) && Str::startsWith($onlyNumbersPhone, '1')) {
            $hasValidCountryPrefix = false;
        }
        if (!$hasValidCountryPrefix && $countryCode) {
            $onlyNumbersPhone = $this->getWhatsAppFormattedPhone($onlyNumbersPhone, $countryCode);
            $onlyNumbersPhone = $this->getOnlyNumbers($onlyNumbersPhone);
        }
        return mb_substr($onlyNumbersPhone, 0, 18);
    }


    public function getWhatsAppFormattedPhoneFromLeadContactPhone(
        LeadContactPhone $leadContactPhone,
        ?Client $client = null
    ): string {
        $client = $client ?? $leadContactPhone->client;
        return $this->formatPhoneForWhatsAppWithSettings(
            $leadContactPhone->phone, $client->country_code, $client->clientSettings ?? null
        );
    }


    public function getWhatsAppFormattedPhone(string $phone, string $countryCode): string
    {
        $countryCode = strtolower(trim($countryCode));
        $phoneNumbers = $this->getOnlyNumbers($phone);
        if (!$phoneNumbers) {
            return '';
        }

        if (Str::startsWith($phoneNumbers, '00')) {
            $phoneNumbers = Str::after($phoneNumbers, '00');
        }
        
        if (Str::startsWith($phoneNumbers, '5401115')) {
            return '54911' . Str::after($phoneNumbers, '5401115');
        }
        if (Str::startsWith($phoneNumbers, '54901115')) {
            return '54911' . Str::after($phoneNumbers, '54901115');
        }
        if (Str::startsWith($phoneNumbers, '549011')) {
            return '54911' . Str::after($phoneNumbers, '549011');
        }
        if (Str::startsWith($phoneNumbers, '54915')) {
            return '54911' . Str::after($phoneNumbers, '54915');
        }
        if (Str::startsWith($phoneNumbers, '54011')) {
            return '54911' . Str::after($phoneNumbers, '54011');
        }
        if (Str::startsWith($phoneNumbers, '5415')) {
            return '54911' . Str::after($phoneNumbers, '5415');
        }
        if (Str::startsWith($phoneNumbers, '54') && !Str::startsWith($phoneNumbers, '549')) {
            return '549' . Str::after($phoneNumbers, '54');
        }
        if (Str::startsWith($phoneNumbers, '52') && !Str::startsWith($phoneNumbers, '521')) {
            return '521' . Str::after($phoneNumbers, '52');
        }

        // Fix Uruguay (global)
        if (Str::startsWith($phoneNumbers, '5980')) {
            return '598' . Str::after($phoneNumbers, '5980');
        }
        if (Str::startsWith($phoneNumbers, '590')) {
            return '598' . Str::after($phoneNumbers, '590');
        }

        // Fix Ecuador (global)
        if (Str::startsWith($phoneNumbers, '5930')) {
            return '593' . Str::after($phoneNumbers, '5930');
        }

        // Fix Uruguay (solo clientes de Uruguay)
        if ($this->isUruguayCountryCode($countryCode)) {
            if (Str::startsWith($phoneNumbers, '0')) {
                $phoneNumbers = Str::after($phoneNumbers, '0');
            }
        }
        // Fix Ecuador (solo clientes de Ecuador)
        if ($this->isEcuadorCountryCode($countryCode)) {
            if (Str::startsWith($phoneNumbers, '0')) {
                $phoneNumbers = Str::after($phoneNumbers, '0');
            }
        }

        if (Str::startsWith($phoneNumbers, '5') && $this->isLatamCountryCode($countryCode)) {
            return $phoneNumbers;
        }

        if ($this->isArgentinaCountryCode($countryCode)) {
            if (Str::startsWith($phoneNumbers, '15')) {
                $phoneNumbers = '11' . Str::after($phoneNumbers, '15');
            }
            if (Str::startsWith($phoneNumbers, '01115')) {
                $phoneNumbers = '11' . Str::after($phoneNumbers, '01115');
            }
            if (Str::startsWith($phoneNumbers, '011')) {
                $phoneNumbers = '11' . Str::after($phoneNumbers, '011');
            }
        }

        $currentCountryCodePrefix = $this->getPrefixByCountryCode($countryCode);
        $startsWithCurrentCountryPrefix = Str::startsWith($phoneNumbers, $currentCountryCodePrefix);
        if ($startsWithCurrentCountryPrefix) {
            return $phoneNumbers;
        }

        $formattedPhone = $currentCountryCodePrefix . $phoneNumbers;
        return $formattedPhone;
    }


    public function getPrefixByCountryCode(string $countryCode): string
    {
        $countryCode = strtolower(trim($countryCode));
        $countryCodesMap = $this->getCountryCodesMap();
        return $countryCodesMap[$countryCode] ?? '54';
    }


    public function phoneHasValidCountryPrefix(string $phone): bool
    {
        $countryCodesMap = $this->getCountryCodesMap();
        foreach ($countryCodesMap as $countryCode => $countryPrefixNumber) {
            // Fix: puede empezar con 1 para estados unidos, pero si empieza con 11, no es válido.
            if (Str::startsWith($phone, '11')) {
                continue;
            }
            // En uruguay le ponen un 0 que no va, si empieza con 5980 lo tomo como inválido
            // (Lo corrije el método getWhatsAppFormattedPhoneFromLeadContactPhone)
            if (Str::startsWith($phone, '5980')) {
                continue;
            }

            // En Perú, los teléfonos empiezan con 9 (y no quiero tomarlo como algun codigo de pais como Turquia)
            if ($this->isPeruCountryCode($countryCode)) {
                if (Str::startsWith($phone, '9')) {
                    continue;
                }
            }

            if (Str::startsWith($phone, $countryPrefixNumber)) {
                return true;
            }
        }
        return false;
    }


    public function getOnlyNumbers(string $phone): string
    {
        $onlyNumbers = preg_replace('/[^0-9]/', '', $phone);
        return $onlyNumbers;
    }


    public function isArgentinaCountryCode(string $countryCode): bool
    {
        $countryCode = strtolower(trim($countryCode));
        $isArgentinaCountryCode = in_array($countryCode, ['ar']);
        return $isArgentinaCountryCode;
    }


    public function isUruguayCountryCode(string $countryCode): bool
    {
        $countryCode = strtolower(trim($countryCode));
        $isUruguayCountryCode = in_array($countryCode, ['uy']);
        return $isUruguayCountryCode;
    }


    public function isPeruCountryCode(string $countryCode): bool
    {
        $countryCode = strtolower(trim($countryCode));
        $isPeruCountryCode = in_array($countryCode, ['pe']);
        return $isPeruCountryCode;
    }


    public function isEcuadorCountryCode(string $countryCode): bool
    {
        $countryCode = strtolower(trim($countryCode));
        $isPeruCountryCode = in_array($countryCode, ['ec']);
        return $isPeruCountryCode;
    }


    public function isBrasilCountryCode(string $countryCode): bool
    {
        $countryCode = strtolower(trim($countryCode));
        $isBrasilCountryCode = in_array($countryCode, ['br']);
        return $isBrasilCountryCode;
    }


    public function isLatamCountryCode(string $countryCode): bool
    {
        $countryCode = strtolower(trim($countryCode));
        $latamCodes = [
            'ar', 'cl', 'co', 'br', 'mx', 'pe', 'py', 've', 'uy', 'bo', 'ec', 'cr', 'pa', 'hn', 'sv', 'gt', 'ni', 'cu'
        ];
        $isLatamCountryCode = in_array($countryCode, $latamCodes);
        return $isLatamCountryCode;
    }


    public function isUSACountryCode(string $countryCode): bool
    {
        $countryCode = strtolower(trim($countryCode));
        $isUSACountryCode = in_array($countryCode, ['us']);
        return $isUSACountryCode;
    }


    public function leadContactPhoneNumberHasValidLength(
        LeadContactPhone $leadContactPhone,
        ?Client $client = null
    ): bool {
        $onlyNumbersPhone = $this->getWhatsAppFormattedPhoneFromLeadContactPhone($leadContactPhone, $client);
        $isValid = $this->formattedPhoneNumberHasValidLength($onlyNumbersPhone);
        return $isValid;
    }

    public function formattedPhoneNumberHasValidLength(string $formattedPhoneNumber): bool
    {
        $onlyNumbersPhone = $this->getOnlyNumbers($formattedPhoneNumber);
        return strlen($onlyNumbersPhone) <= 14 && strlen($onlyNumbersPhone) >= 10;
    }


    public function getCountryCodesMap(): array
    {
        return [
            'ar' => '549',
            'cl' => '56',
            'co' => '57',
            'br' => '55',
            'mx' => '521',
            'pe' => '51',
            'py' => '595',
            've' => '58',
            'uy' => '598',
            'bo' => '591',
            'ec' => '593',
            'cr' => '506',
            'pa' => '507',
            'hn' => '504',
            'sv' => '503',
            'gt' => '502',
            'us' => '1',
            'es' => '34',
            'fr' => '33',
            'it' => '39',
            'be' => '32',
            'nl' => '31',
            'de' => '49',
            'uk' => '44',
            'ad' => '376',
            'ie' => '353',
            'pt' => '351',
            'ch' => '41',
            'ni' => '505',
            'do' => '1',
            'cu' => '53',
            'tr' => '90',
            'il' => '972',
        ];
    }

}
