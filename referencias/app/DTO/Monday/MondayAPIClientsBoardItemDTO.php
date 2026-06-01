<?php

namespace App\DTO\Monday;

use Illuminate\Support\Str;


class MondayAPIClientsBoardItemDTO
{

    public string $id;
    public string $name;
    public array $group;
    

    public ?string $leadId = null;
    public ?string $status = null;
    public ?string $businessName = null;
    public ?string $entryDate = null;
    public ?float $a1 = null;
    public ?float $a2 = null;
    public ?float $exchangeRate = null;
    public ?float $discount = null;
    public ?string $a3 = null;
    public ?string $contactName = null;
    public ?string $contactEmail = null;
    public ?string $clientType = null;
    public ?string $businessType = null;
    public ?string $businessArea = null;
    public ?string $pain = null;
    public ?string $cons = null;
    public ?string $durationMonths = null;
    public ?string $country = null;
    public ?string $contactPhone = null;
    public ?array $seller = null;
    public ?array $channel = null;
    public ?array $adSeminar = null;
    public ?int $quality = null;
    public ?int $interest = null;
    public ?string $churnDate = null;
    public ?string $churnReason = null;
    public ?float $lifetimeValue = null;
    public ?string $clientyClientUrl = null;
    public ?string $clientyClientSubdomain = null;
    public string $createdAt;
    public string $updatedAt;
    

    public array $columnMap = [
        'id8__1' => ['property' => 'leadId', 'title' => 'ID'],
        'estado_mkmsvvqn' => ['property' => 'status', 'title' => 'Estado'],
        'texto4' => ['property' => 'businessName', 'title' => 'RAZON SOCIAL'],
        'fecha5' => ['property' => 'entryDate', 'title' => 'Fecha de ingreso'],
        'numbers' => ['property' => 'a1', 'title' => 'A1'],
        'abono_usd' => ['property' => 'a2', 'title' => 'A2'],
        'n_meros7' => ['property' => 'exchangeRate', 'title' => 'TC'],
        'n_meros0' => ['property' => 'discount', 'title' => 'bonif'],
        'f_rmula' => ['property' => 'a3', 'title' => 'A3'],
        'texto' => ['property' => 'contactName', 'title' => 'Nombre'],
        'contact_details' => ['property' => 'contactEmail', 'title' => 'Mail'],
        'status0' => ['property' => 'clientType', 'title' => 'Tipo de cliente'],
        'status7' => ['property' => 'businessType', 'title' => 'B2B - B2C'],
        'rubro_mkmttagz' => ['property' => 'businessArea', 'title' => 'Rubro'],
        'pain__1' => ['property' => 'pain', 'title' => 'Pain'],
        'texto_largo7' => ['property' => 'cons', 'title' => 'Contras'],
        'f_rmula8' => ['property' => 'durationMonths', 'title' => 'Mes'],
        'pa_s' => ['property' => 'country', 'title' => 'País'],
        'tel_fono' => ['property' => 'contactPhone', 'title' => 'Teléfono'],
        'tags' => ['property' => 'seller', 'title' => 'Vendedor'],
        'etiquetas03' => ['property' => 'channel', 'title' => 'Canal'],
        'etiquetas_1' => ['property' => 'adSeminar', 'title' => 'Ad/Seminario'],
        'rating1' => ['property' => 'quality', 'title' => 'Calidad'],
        'dup__of_calidad' => ['property' => 'interest', 'title' => 'Interes'],
        'fecha1' => ['property' => 'churnDate', 'title' => 'Fecha de baja'],
        'estado__1' => ['property' => 'churnReason', 'title' => 'Motivo de baja'],
        'f_rmula__1' => ['property' => 'lifetimeValue', 'title' => 'LTV'],
        'enlace_mkmtdb0f' => ['property' => 'clientyClientUrl', 'title' => 'Link CRM']
    ];


    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->group = $data['group'];
        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
        
        $this->initializeColumnValues($data['column_values']);
    }


    public function getColumnTitleById(string $columnId): ?string
    {
        return $this->columnMap[$columnId]['title'] ?? null;
    }
    

    public function getColumnIdByTitle(string $columnTitle): ?string
    {
        foreach ($this->columnMap as $columnId => $columnData) {
            if ($columnData['title'] === $columnTitle) {
                return $columnId;
            }
        }
        return null;
    }


    public function initializeColumnValues(array $columnValues): void
    {
        foreach ($columnValues as $columnValue) {
            $columnId = $columnValue['id'];
            if (!isset($this->columnMap[$columnId])) {
                continue;
            }
            $propertyName = $this->columnMap[$columnId]['property'];
            $value = $this->parseColumnValue($columnValue);
            $this->$propertyName = $value;
        }

        if ($this->clientyClientUrl) {
            $host = parse_url($this->clientyClientUrl, PHP_URL_HOST);
            $this->clientyClientSubdomain = Str::before($host, '.');
        }
    }


    public function parseColumnValue(array $columnValue): mixed
    {
        $type = $columnValue['type'];
        $value = $columnValue['value'];
        if ($value === null || $value === '{}' || $value === '""') {
            return null;
        }

        switch ($type) {
            case 'text':
                return json_decode($value, true);
            case 'numbers':
                $numValue = json_decode($value, true);
                return $numValue === "0" ? 0.0 : (float)$numValue;
            case 'status':
                return $columnValue['label'] ?? null;
            case 'date':
                return $columnValue['date'] ?? null;
            case 'email':
                return $columnValue['email'] ?? null;
            case 'long_text':
                return $columnValue['text'] ?? null;
            case 'country':
                $countryData = json_decode($value, true);
                return $countryData['countryName'] ?? null;
            case 'phone':
                $phoneData = json_decode($value, true);
                return $phoneData['phone'] ?? null;
            case 'link':
                return $columnValue['url'] ?? null;
            case 'people':
                return json_decode($value, true)['personsAndTeams'] ?? null;
            case 'rating':
                $ratingValue = json_decode($value, true);
                return $ratingValue ? (int)$ratingValue : null;
            case 'tags':
            case 'subtasks':
                return json_decode($value, true) ?: null;
            default:
                return $value;
        }
    }


    public function getValueByColumnName(string $columnName): mixed
    {
        foreach ($this->columnMap as $columnId => $columnData) {
            if ($columnData['title'] === $columnName) {
                $property = $columnData['property'];
                return $this->$property;
            }
        }
        return null;
    }


    public function getValueByColumnId(string $columnId): mixed
    {
        if (!isset($this->columnMap[$columnId])) {
            return null;
        }
        $property = $this->columnMap[$columnId]['property'];
        return $this->$property;
    }

}