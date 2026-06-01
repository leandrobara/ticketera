<?php

namespace App\DTO\Import\SpreadSheetLeadSaleImport;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\DTO\Import\ImportLeadDTOInterface;
use App\DTO\Import\SpreadSheetLeadImport\SpreadSheetImportLeadContactDTO;


class SpreadSheetImportLeadSaleDTO implements ImportLeadDTOInterface
{

    public $leadId = null;
    public $amount = null;
    public $saleDate = null;
    public $description = null;

    public $leadWasFound = false;
    public $amountIsEmpty = true;
    public $leadIdIsEmpty = true;
    public $saleDateIsEmpty = true;
    public $amountFormatIsWrong = true;
    public $descriptionIsTooLong = true;
    public $salesDateFormatIsWrong = true;

    public $warningReasons = [];
    public $nonPersistableReasons = [];

    const AMOUNT_IS_EMPTY = 'amount_is_empty';
    const LEAD_ID_IS_EMPTY = 'lead_id_is_empty';
    const NON_EXISTENT_LEAD = 'non_existent_lead';
    const SALE_DATE_IS_EMPTY = 'sale_date_is_empty';
    const AMOUNT_FORMAT_IS_WRONG = 'amount_format_is_wrong';
    const DESCRIPTION_IS_TOO_LONG = 'description_is_too_long';
    const SALE_DATE_FORMAT_IS_WRONG = 'sale_date_format_is_wrong';


    public function __construct(array $arrayLeadSale, array $headers)
    {
        $arrayLeadSale = $this->cleanArrayValues($arrayLeadSale);

        $this->leadId = trim($arrayLeadSale[0]);
        $this->amount = trim($arrayLeadSale[1]);
        $this->saleDate = trim($arrayLeadSale[2]);
        $this->description = trim($arrayLeadSale[3]);
    }


    public function getNonPersistableReasons(): array
    {
        return $this->nonPersistableReasons;
    }


    public function addNonPersistibleReason(string $reason): SpreadSheetImportLeadSaleDTO
    {
        $allowedReasons = [
            self::AMOUNT_IS_EMPTY,
            self::LEAD_ID_IS_EMPTY,
            self::NON_EXISTENT_LEAD,
            self::SALE_DATE_IS_EMPTY,
            self::AMOUNT_FORMAT_IS_WRONG,
            self::SALE_DATE_FORMAT_IS_WRONG,
        ];
        if (!in_array($reason, $allowedReasons)) {
            throw new Exception('SpreadSheetImportLeadDTO invalid import status');
        }
        $this->nonPersistableReasons[] = $reason;
        return $this;
    }


    public function addWarningReason(string $reason): SpreadSheetImportLeadSaleDTO
    {
        $allowedReasons = [
            self::DESCRIPTION_IS_TOO_LONG,
        ];
        if (!in_array($reason, $allowedReasons)) {
            throw new Exception('SpreadSheetUpdateLeadDTO invalid update warning status');
        }
        $this->warningReasons[] = $reason;
        return $this;
    }


    private function cleanArrayValues($arrayValues)
    {
        $arrayValues = array_map(function ($val) {
            if (is_string($val)) {
                // Limpio los caracteres extraños (códigos ASCII menores que 32 y mayores que 126)
                $val = preg_replace('/[\x00-\x1F\x7F-\xFF&&[^áéíóúÁÉÍÓÚÜü]]/', '', $val);
                return trim($val);
            }
            return $val;
        }, $arrayValues);
        // $arrayValues = array_map("trim", $arrayValues);

        // remove \n\s to certain fields
        $arrayValues[1] = preg_replace("/\r|\n/", "", $arrayValues[1]);
        $arrayValues[2] = preg_replace("/\r|\n/", "", $arrayValues[2]);
        $arrayValues[3] = preg_replace("/\r|\n/", "", $arrayValues[3]);
        return $arrayValues;
    }

}
