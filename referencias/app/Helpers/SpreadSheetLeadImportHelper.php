<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Traits\GetClientFromRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\DTO\Import\SpreadSheetLeadImport\SpreadSheetImportLeadDTO;
use App\Exceptions\Helpers\SpreadSheetLeadImportHelper\InvalidHeadersException;
use App\Exceptions\Helpers\SpreadSheetLeadImportHelper\UnsupportedFileException;


class SpreadSheetLeadImportHelper
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
            'Canal de adquisición',
            'Estado',
            'Empresa',
            'Consulta',
            'Notas',
            'Etiquetas',
            'Asignado a',
            'Contacto 1 - Nombre',
            'Contacto 1 - Apellido',
            'Contacto 1 - Email 1',
            'Contacto 1 - Email 2',
            'Contacto 1 - Teléfono 1',
            'Contacto 1 - Teléfono 2',
            'Contacto 2 - Nombre',
            'Contacto 2 - Apellido',
            'Contacto 2 - Email 1',
            'Contacto 2 - Email 2',
            'Contacto 2 - Teléfono 1',
            'Contacto 2 - Teléfono 2',
            'Contacto 3 - Nombre',
            'Contacto 3 - Apellido',
            'Contacto 3 - Email 1',
            'Contacto 3 - Email 2',
            'Contacto 3 - Teléfono 1',
            'Contacto 3 - Teléfono 2',
            'Contacto 4 - Nombre',
            'Contacto 4 - Apellido',
            'Contacto 4 - Email 1',
            'Contacto 4 - Email 2',
            'Contacto 4 - Teléfono 1',
            'Contacto 4 - Teléfono 2',
            'Campo personalizado: XXXX',
            'Campo personalizado: XXXX',
            'Campo personalizado: XXXX',
            'Campo personalizado: XXXX',
            'Campo personalizado: XXXX',
        ];

        $headers = $spreadSheetArray[0];
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
            $dto = new SpreadSheetImportLeadDTO($row, $headers);
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
