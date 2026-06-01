<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Traits\GetClientFromRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\DTO\Import\SpreadSheetLeadUpdate\SpreadSheetUpdateLeadDTO;
use App\Exceptions\Helpers\SpreadSheetLeadImportHelper\InvalidHeadersException;
use App\Exceptions\Helpers\SpreadSheetLeadImportHelper\UnsupportedFileException;


class SpreadSheetLeadUpdateHelper
{

    use GetClientFromRequest;

    private const CSV_MIME = 'text/csv';
    private const XLS_MIME = 'application/vnd.ms-excel';
    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';


    public function parseFile(UploadedFile $file): Collection
    {
        $mimeType = $file->getClientMimeType();
        if ($mimeType !== self::XLSX_MIME && $mimeType !== self::XLS_MIME && $mimeType !== self::CSV_MIME) {
            throw new UnsupportedFileException();
        }

        $spreadSheetArray = (IOFactory::load($file->getRealPath()))->getActiveSheet()->toArray();

        $this->validateSpreadSheet($spreadSheetArray);
        $dtos = $this->createDTOCollection($spreadSheetArray);
        return $dtos;
    }


    private function validateSpreadSheet(array $spreadSheetArray): bool
    {
        $validHeaders = [
            'ID de prospecto',
            'Canal de adquisición',
            'Empresa',
            'Notas',
            'Asignado a',
            'Contacto - Nombre',
            'Contacto - Apellido',
            'Contacto - Email',
            'Contacto - Teléfono',
            'Campo personalizado: XXXX',
            'Campo personalizado: XXXX',
            'Campo personalizado: XXXX',
        ];

        $headers = array_filter($spreadSheetArray[0], fn ($val) => $val);
        foreach ($headers as $headerCellValue) {
            $headerCellValue = trim($headerCellValue);
            $isCustomField = Str::contains($headerCellValue, 'Campo personalizado:');
            if (!$isCustomField && !in_array($headerCellValue, $validHeaders)) {
                throw new InvalidHeadersException('invalid_headers', 413);
            }
        }
        return true;
    }


    private function createDTOCollection(array $spreadSheetArray): Collection
    {
        // Remove headers row
        $headers = array_shift($spreadSheetArray);

        $dtos = new Collection();
        foreach ($spreadSheetArray as $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }
            $dto = new SpreadSheetUpdateLeadDTO($row, $headers);
            $dtos->add($dto);
        }
        return $dtos;
    }


    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            // string no vacía después de trim
            if (is_string($cell)) {
                if (trim($cell) !== '') {
                    return false;
                }
            // cualquier valor no nulo / no false / no 0-length array
            } elseif ($cell !== null && $cell !== '' && $cell !== false) {
                return false;
            }
        }
        return true;
    }
}
