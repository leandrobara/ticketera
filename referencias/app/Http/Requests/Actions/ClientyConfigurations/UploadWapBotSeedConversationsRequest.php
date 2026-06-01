<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use Carbon\Carbon;
use App\Http\Requests\APIBaseRequest;
use App\DTO\ClientyConfigurations\WapBotSeedConversationsUploadDTO;


class UploadWapBotSeedConversationsRequest extends APIBaseRequest
{

    private const MAX_ROWS = 2000;
    private const MIN_PHONE_DIGITS = 6;

    private array $validRows = [];
    private int $discardedRows = 0;


    public function rules()
    {
        return [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5MB max
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                $clientyClientId = (int) config('app.clienty.client_id');
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }

                // Validar y parsear el CSV
                $this->parseAndValidateCsv($validator);
            });
        }
    }


    private function parseAndValidateCsv($validator): void
    {
        $file = request()->file('csv_file');
        
        if (!$file) {
            $validator->errors()->add('csv_file', 'no_file_uploaded');
            return;
        }

        $csvData = array_map('str_getcsv', file($file->getRealPath()));
        
        // Ignorar primera línea (headers)
        if (count($csvData) > 0) {
            array_shift($csvData);
        }

        if (count($csvData) > self::MAX_ROWS) {
            $validator->errors()->add('csv_file', 'max_rows_exceeded');
            return;
        }

        $this->validRows = [];
        $this->discardedRows = 0;

        foreach ($csvData as $row) {
            if (count($row) < 3) {
                $this->discardedRows++;
                continue;
            }

            // Limpiar comillas y espacios
            $phoneNumber = trim($row[0], " \t\n\r\0\x0B\"'");
            $timestamp = trim($row[1], " \t\n\r\0\x0B\"'");
            $date = trim($row[2], " \t\n\r\0\x0B\"'");

            // Validar teléfono (debe ser numérico y tener más de 6 dígitos)
            if (!is_numeric($phoneNumber) || strlen($phoneNumber) <= self::MIN_PHONE_DIGITS) {
                $this->discardedRows++;
                continue;
            }

            // Validar timestamp (debe ser numérico)
            if (!is_numeric($timestamp)) {
                $this->discardedRows++;
                continue;
            }

            // Validar fecha (debe ser parseable)
            try {
                $parsedDate = Carbon::parse($date);
            } catch (\Exception $e) {
                $this->discardedRows++;
                continue;
            }

            $this->validRows[] = [
                'customerPhoneNumber' => $phoneNumber,
                'lastActivityTimestamp' => $timestamp,
                'lastActivityDate' => $parsedDate,
            ];
        }

        if (empty($this->validRows)) {
            $validator->errors()->add('csv_file', 'no_valid_rows_found');
            return;
        }
    }


    public function getDTO(): WapBotSeedConversationsUploadDTO
    {
        return WapBotSeedConversationsUploadDTO::fromArray($this->validRows);
    }


    public function getDiscardedRowsCount(): int
    {
        return $this->discardedRows;
    }

}

