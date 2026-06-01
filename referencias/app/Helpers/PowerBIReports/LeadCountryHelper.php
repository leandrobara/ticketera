<?php

namespace App\Helpers\PowerBIReports;

use Exception;
use App\Models\Lead;


class LeadCountryHelper
{

    protected $customFieldCountryMap = [
        'america/argentina/buenos_aires' => 'Argentina',
        'america/argentina/san_luis' => 'Argentina',
        'america/asuncion' => 'Paraguay',
        'america/bogota' => 'Colombia',
        'america/caracas' => 'Venezuela',
        'america/chicago' => 'Estados Unidos',
        'america/denver' => 'Estados Unidos',
        'america/guatemala' => 'Guatemala',
        'america/guayaquil' => 'Ecuador',
        'america/la_paz' => 'Bolivia',
        'america/lima' => 'Perú',
        'america/los_angeles' => 'Estados Unidos',
        'america/mazatlan' => 'Mexico',
        'america/mexico city' => 'Mexico',
        'america/mexico_city' => 'Mexico',
        'america/montevideo' => 'Uruguay',
        'america/new_york' => 'Estados Unidos',
        'america/phoenix' => 'Estados Unidos',
        'america/santiago' => 'Chile',
        'america/santo_domingo' => 'República Dominicana',
        'america/sao_paulo' => 'Brasil',
        'america/uruguay' => 'Uruguay',
        'argentina' => 'Argentina',
        'asia/bangkok' => 'Tailandia',
        'asia/dubai' => 'Emiratos Arabes Unidos',
        'asia/shanghai' => 'China',
        'australia' => 'Australia',
        'bolivia' => 'Bolivia',
        'chile' => 'Chile',
        'colombia' => 'Colombia',
        'costa rica' => 'Costa Rica',
        'ecuador' => 'Ecuador',
        'el salvador' => 'El Salvador',
        'españa' => 'España',
        'europe/berlin' => 'Alemania',
        'europe/london' => 'Reino Unido',
        'guatemala' => 'Guatemala',
        'honduras' => 'Honduras',
        'mexico' => 'Mexico',
        'nicaragua' => 'Nicaragua',
        'panama' => 'Panamá',
        'paraguay' => 'Paraguay',
        'peru' => 'Perú',
        'perú' => 'Perú',
        'republica dominicana' => 'República Dominicana',
        'republica dominica' => 'República Dominicana',
        'república dominicana' => 'República Dominicana',
        'uruguay' => 'Uruguay',
        'venezuela' => 'Venezuela',
    ];

    protected $tagCountryMap = [
        'argentina' => 'Argentina',
        'bolivia' => 'Bolivia',
        'brasil' => 'Brasil',
        'chile' => 'Chile',
        'colombia' => 'Colombia',
        'costa rica' => 'Costa Rica',
        'ecuador' => 'Ecuador',
        'el salvador' => 'El Salvador',
        'españa' => 'España',
        'francia' => 'Francia',
        'guatemala' => 'Guatemala',
        'honduras' => 'Honduras',
        'italia' => 'Italia',
        'mexico' => 'Mexico',
        'nicaragua' => 'Nicaragua',
        'panamá' => 'Panamá',
        'paraguay' => 'Paraguay',
        'perú' => 'Perú',
        'puerto rico' => 'Puerto Rico',
        'república dominicana' => 'República Dominicana',
        'uk' => 'Reino Unido',
        'uruguay' => 'Uruguay',
        'usa' => 'Estados Unidos',
        'venezuela' => 'Venezuela',
    ];


    public function getCountryName(Lead $lead): ?string
    {
        foreach ($lead->tags as $tag) {
            $tagNameLower = strtolower($tag->name);
            if (array_key_exists($tagNameLower, $this->tagCountryMap)) {
                return $this->tagCountryMap[$tagNameLower];
            }
        }
        foreach ($lead->leadCustomFieldsValues as $customFieldValue) {
            $fieldNameLower = strtolower($customFieldValue->leadCustomField->name);
            if (array_key_exists($fieldNameLower, $this->customFieldCountryMap)) {
                return $this->customFieldCountryMap[$fieldNameLower];
            }
        }
        return null;
    }

}
