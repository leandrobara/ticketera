<?php

namespace App\DTO\Monday;

use DateTime;
use Exception;
use App\Models\Client;
use Illuminate\Support\Str;


/**
 * @todo Unificar esto (hecho para MondayAPIHelper2) con MondayAPICelulaOBBoardClientDTO (hecho para MondayAPIHelper)
 */
class MondayAPICelulaOBBoardClientLastWeeksHitsDTO
{

    public ?string $id;
    public ?string $name;
    public ?string $status;
    public ?string $subdomain;
    public Client $clientyClient;
    public ?string $situationStatus;

    public ?int $averageHits;
    public ?int $hitsCountLastWeek;
    public ?int $hitsCountTwoWeeksAgo;
    public ?int $hitsCountFourWeeksAgo;
    public ?int $hitsCountThreeWeeksAgo;

    private array $rawClientData;


    public function __construct(array $mondayClientData)
    {
        // $this->rawClientData = $mondayClientData;
        $this->id = $mondayClientData['id'];
        $this->name = $mondayClientData['name'];
        $this->subdomain = $this->getSubdomainValue($mondayClientData['column_values']);
        $this->situationStatus = $this->findColumnLabelById($mondayClientData['column_values'], 'estado9__1');
        $this->status = $this->findColumnDisplayValueById($mondayClientData['column_values'], 'dup__of_ingreso1');

        $this->averageHits = $this->getHitsValue($mondayClientData['column_values'], 'n_meros2__1');
        $this->hitsCountLastWeek = $this->getHitsValue($mondayClientData['column_values'], 'n_meros');
        $this->hitsCountTwoWeeksAgo = $this->getHitsValue($mondayClientData['column_values'], 'n_meros8__1');
        $this->hitsCountFourWeeksAgo = $this->getHitsValue($mondayClientData['column_values'], 'n_meros6__1');
        $this->hitsCountThreeWeeksAgo = $this->getHitsValue($mondayClientData['column_values'], 'n_meros4__1');
    }

    
    public function getHitsSum(): int
    {
        $total = $this->hitsCountLastWeek +
            $this->hitsCountTwoWeeksAgo +
            $this->hitsCountFourWeeksAgo +
            $this->hitsCountThreeWeeksAgo
        ;
        return $total;
    }


    private function getHitsValue(array $columnValues, string $columnId): ?string
    {
        $hits = $this->findColumnValueById($columnValues, $columnId);
        if ($hits === null || $hits === '') {
            return null;
        }
        return (int) $hits;
    }


    private function getSubdomainValue(array $columnValues): ?string
    {
        $clientyUrl = $this->findColumnDisplayValueById($columnValues, 'dup__of_ingreso__1');
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

    
    private function findColumnById(array $columnValues, string $id): ?array
    {
        foreach ($columnValues as $column) {
            $columnId = $column['column']['id'] ?? null;
            if ($columnId === $id) {
                return $column;
            }
        }
        return null;
    }


    private function findColumnByTitle(array $columnValues, string $title): ?array
    {
        foreach ($columnValues as $column) {
            $columnTitle = $column['column']['title'] ?? null;
            if ($columnTitle === $title) {
                return $column;
            }
        }
        return null;
    }


    private function findColumnValueById(array $columnValues, string $id): ?string
    {
        $column = $this->findColumnById($columnValues, $id);
        return $column['value'] ?? null;
    }


    private function findColumnValueByTitle(array $columnValues, string $title): ?string
    {
        $column = $this->findColumnByTitle($columnValues, $id);
        return $column['value'] ?? null;
    }


    private function findColumnLabelById(array $columnValues, string $id): ?string
    {
        $column = $this->findColumnById($columnValues, $id);
        return $column['label'] ?? null;
    }

    private function findColumnDisplayValueById(array $columnValues, string $id): ?string
    {
        $column = $this->findColumnById($columnValues, $id);
        return $column['display_value'] ?? null;
    }

}