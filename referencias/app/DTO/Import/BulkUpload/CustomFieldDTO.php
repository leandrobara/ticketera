<?php

namespace App\DTO\Import\BulkUpload;

use Exception;
use Illuminate\Support\Str;
use App\Models\LeadCustomField;
use Illuminate\Support\Collection;


class CustomFieldDTO
{

    public $value = null;
    public $leadCustomField = null;


    public static function build(LeadCustomField $leadCustomField, string $value): CustomFieldDTO
    {
        $dto = new CustomFieldDTO();
        if (trim($value) === '') {
            throw new Exception('CustomFieldDTO: value can not be empty.');
        }
        $dto->value = Str::limit($value, 250, '');
        $dto->leadCustomField = $leadCustomField;
        return $dto;
    }

}
