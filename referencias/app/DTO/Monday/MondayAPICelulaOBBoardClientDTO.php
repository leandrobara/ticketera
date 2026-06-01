<?php

namespace App\DTO\Monday;

use DateTime;
use Exception;
use App\Models\Client;
use Illuminate\Support\Str;


/**
 * @todo Unificar esto (hecho para MondayAPIHelper) con
 * MondayAPICelulaOBBoardClientLastWeeksHitsDTO (hecho para MondayAPIHelper2)
 */
class MondayAPICelulaOBBoardClientDTO
{

    public ?string $id;
    public ?string $name;
    public ?string $status;
    public ?string $subdomain;
    public Client $clientyClient;
    public ?string $situationStatus;

    public ?string $totalHitsFlagStr;
    public ?string $manualLeadsFlagStr;
    public ?string $statusChangesFlagStr;
    public ?string $automaticLeadsFlagStr;

    public bool $totalHitsFlagIsSuccess;
    public bool $manualLeadsFlagIsSuccess;
    public bool $statusChangesFlagIsSuccess;
    public bool $automaticLeadsFlagIsSuccess;

    private array $rawClientData;


    public function __construct(array $clientData)
    {
        $this->rawClientData = $clientData;

        $this->id = $clientData['id'];
        $this->name = $clientData['name'];
        $this->status = $this->getStatusValue($clientData['column_values']);
        $this->subdomain = $this->getSubdomainValue($clientData['column_values']);
        $this->situationStatus = $this->getSituationStatusValue($clientData['column_values']);

        $this->manualLeadsFlagStr = $this->findColumnValueById($clientData['column_values'], 'bbdd');
        $this->totalHitsFlagStr = $this->findColumnValueById($clientData['column_values'], 'hits_20_dias');
        $this->statusChangesFlagStr = $this->findColumnValueById($clientData['column_values'], 'cambia_estados');
        $this->automaticLeadsFlagStr = $this->findColumnValueById($clientData['column_values'], 'carga_autom_tica');

        $this->totalHitsFlagIsSuccess = $this->totalHitsFlagStr == 'Realizado';
        $this->manualLeadsFlagIsSuccess = $this->manualLeadsFlagStr == 'Realizado';
        $this->statusChangesFlagIsSuccess = $this->statusChangesFlagStr == 'Realizado';
        $this->automaticLeadsFlagIsSuccess = $this->automaticLeadsFlagStr == 'Realizado';
    }


    private function getStatusValue(array $columnValues): ?string
    {
        return $this->findColumnValueByTitle($columnValues, 'ESTADO');
    }


    private function getSubdomainValue(array $columnValues): ?string
    {
        $clientyUrl = $this->findColumnValueByTitle($columnValues, 'Link CRM');
        if (!$clientyUrl) {
            return null;
        }
        $host = parse_url($clientyUrl, PHP_URL_HOST);
        $subdomain = Str::before($host, '.');
        return $subdomain;
    }


    private function getSituationStatusValue(array $columnValues): ?string
    {
        $situationStatus = $this->findColumnValueById($columnValues, 'estado9__1');
        if (!$situationStatus) {
            return null;
        }
        return $situationStatus;
    }


    private function findColumnValueById(array $columnValues, string $id): ?string
    {
        foreach ($columnValues as $columnValue) {
            if ($columnValue['id'] === $id) {
                return $columnValue['text'];
            }
        }
        return null;
    }


    private function findColumnValueByTitle(array $columnValues, string $title): ?string
    {
        foreach ($columnValues as $columnValue) {
            if (strtolower($columnValue['title']) == strtolower($title)) {
                return $columnValue['text'];
            }
        }
        return null;
    }


    public function toArray(): array
    {
        return $this->clientData;
    }

}