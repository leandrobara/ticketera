<?php

namespace App\DTO\Monday;

use DateTime;
use Exception;

class MondayAPIChurnBoardClientDTO
{

    private array $transformedData;


    public function __construct(array $churnBoardClientData)
    {
        $this->transformedData = $churnBoardClientData;
        $this->transformData();
    }


    private function transformData(): void
    {
        $columnValues = $this->transformedData['column_values'];

        $this->transformedData['formattedValues'] = [
            'name' => $this->getNameValue($columnValues),
            'email' => $this->getEmailValue($columnValues),
            'reason' => $this->getReasonValue($columnValues),
            'status' => $this->getStatusValue($columnValues),
            'onboarder' => $this->getOnboarderValue($columnValues),
            'entryDate' => $this->getEntryDateValue($columnValues),
            'clientType' => $this->getClientTypeValue($columnValues),
            'businessArea' => $this->getBusinessAreaValue($columnValues),
            'modifiedDate' => $this->getModificationDateValue($columnValues),
            'alternativeName' => $this->getAlternativeNameValue($columnValues),
            'modifiedDateTs' => $this->getModificationDateTsValue($columnValues),
            'activeMonthsCount' => $this->getActiveMonthsCountValue($columnValues),
        ];
        $this->transformedData['externalId'] = $this->transformedData['id'];
        unset($this->transformedData['id']);
        // unset($this->transformedData['column_values']);
        // unset($this->transformedData['mappable_column_values']);
    }


    private function getNameValue(array $columnValues): ?string
    {
        $generalClientesCol = $this->findColumnValueById($columnValues, 'general___clientes__1');
        $conectarTablerosCol = $this->findColumnValueById($columnValues, 'conectar_tableros__1');
        return !empty($generalClientesCol['text']) ? $generalClientesCol['text'] : $conectarTablerosCol['text'];
    }


    private function getAlternativeNameValue(array $columnValues): ?string
    {
        $conectarTablerosCol = $this->findColumnValueById($columnValues, 'conectar_tableros__1');
        return !empty($conectarTableroCol['text']) ? $conectarTablerosCol['text'] : null;
    }


    private function getStatusValue(array $columnValues): ?string
    {
        $statusCol = $this->findColumnValueById($columnValues, 'status__1');
        return !empty($statusCol['text']) ? $statusCol['text'] : null;
    }


    private function getEmailValue(array $columnValues): ?string
    {
        $lookup2Col = $this->findColumnValueById($columnValues, 'lookup2__1');
        $dupOfMailCol = $this->findColumnValueById($columnValues, 'dup__of_mail__1');
        $correoCol = $this->findColumnValueById($columnValues, 'correo_electr_nico__1');

        if ($correoCol['text'] ?? null) {
            if (filter_var($correoCol['text'], FILTER_VALIDATE_EMAIL)) {
                return $correoCol['text'];
            }
        }
        if ($dupOfMailCol['text'] ?? null) {
            if (filter_var($dupOfMailCol['text'], FILTER_VALIDATE_EMAIL)) {
                return $dupOfMailCol['text'];
            }
        }
        if ($lookup2Col['text'] ?? null) {
            if (filter_var($lookup2Col['text'], FILTER_VALIDATE_EMAIL)) {
                return $lookup2Col['text'];
            }
        }
        return null;
    }


    private function getBusinessAreaValue(array $columnValues): ?string
    {
        $businessAreaCol = $this->findColumnValueById($columnValues, 'dup__of_mail__1');
        return !empty($businessAreaCol['text']) ? $businessAreaCol['text'] : null;
    }


    private function getModificationDateValue(array $columnValues): ?string
    {
        $modificationDateCol = $this->findColumnValueById($columnValues, 'fecha_modificacion9__1');
        $modifiedDateStr = !empty($modificationDateCol['text']) ? $modificationDateCol['text'] : null;
        if (!$modifiedDateStr) {
            return null;
        }
        return (new DateTime($modifiedDateStr))->format('Y-m-d');
    }


    private function getEntryDateValue(array $columnValues): ?string
    {
        $entryDateCol = $this->findColumnValueById($columnValues, 'dup__of_mes__1');
        $entryDateStr = !empty($entryDateCol['text']) ? $entryDateCol['text'] : null;
        if (!$entryDateStr) {
            return null;
        }
        return (new DateTime($entryDateStr))->format('Y-m-d');
    }


    private function getModificationDateTsValue(array $columnValues): ?int
    {
        $modificationDateCol = $this->findColumnValueById($columnValues, 'fecha_modificacion9__1');
        $modifiedDateStr = !empty($modificationDateCol['text']) ? $modificationDateCol['text'] : null;
        if (!$modifiedDateStr) {
            return null;
        }
        return (new DateTime($modifiedDateStr))->getTimestamp();
    }


    private function getActiveMonthsCountValue(array $columnValues): ?int
    {
        $activeMonthsCountCol = $this->findColumnValueById($columnValues, 'mes__1');
        return !empty($activeMonthsCountCol['text']) ? (int) $activeMonthsCountCol['text'] : null;
    }


    private function getReasonValue(array $columnValues): ?string
    {
        $reason = $this->findColumnValueById($columnValues, 'motivo7__1');
        return !empty($reason['text']) ? $reason['text'] : null;
    }


    private function getClientTypeValue(array $columnValues): ?string
    {
        $fieldArr = $this->findColumnValueById($columnValues, 'reflejo__1');
        return !empty($fieldArr['text']) ? $fieldArr['text'] : null;
    }


    private function getOnboarderValue(array $columnValues): ?string
    {
        $fieldArr = $this->findColumnValueById($columnValues, 'dup__of_a35__1');
        return !empty($fieldArr['text']) ? $fieldArr['text'] : null;
    }


    private function findColumnValueById(array $columnValues, string $id): array
    {
        foreach ($columnValues as $columnValue) {
            if ($columnValue['id'] === $id) {
                return $columnValue;
            }
        }
        return ['text' => ''];
    }


    public function hasUnsubscribeStatus(): bool
    {
        $statusName = $this->transformedData['formattedValues']['status'];
        if (!$statusName) {
            return false;
        }
        return trim(strtolower($statusName)) == 'baja';
    }


    public function hasName(): bool
    {
        $name = $this->transformedData['formattedValues']['name'];
        $alternativeName = $this->transformedData['formattedValues']['alternativeName'];
        return ($name || $alternativeName);
    }


    public function hasEmail(): bool
    {
        $email = $this->transformedData['formattedValues']['email'];
        return $email ? true : false;
    }


    public function getExternalId(): string
    {
        return $this->transformedData['externalId'];
    }


    public function toArray(): array
    {
        return $this->transformedData;
    }

}