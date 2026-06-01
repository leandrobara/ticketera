<?php

namespace App\DTO\Import\BulkUpload;

use Exception;
use App\Models\Lead;
use Illuminate\Support\Collection;


class BulkUploadLeadSaleDataDTO
{

    public $lead = null;
    public $amount = null;
    public $saleDate = null;
    public $description = null;


    private function __construct()
    {
    }


    public static function build(array $params): BulkUploadLeadSaleDataDTO
    {
        $dto = new BulkUploadLeadSaleDataDTO();

        self::validateBuildParams($params);
        
        $dto->lead = $params['lead'];
        $dto->amount = $params['amount'];
        $dto->saleDate = $params['sale_date'];
        $dto->description = $params['description'] ?? null;
        return $dto;
    }


    public function getLeadSaleAttrs(): array
    {
        return [
            'lead' => $this->lead,
            'amount' => $this->amount,
            'sale_date' => $this->saleDate,
            'description' => $this->description,
        ];
    }


    private static function validateBuildParams(array $params): void
    {
        $lead = $params['lead'] ?? null;
        $amount = $params['amount'] ?? null;
        $saleDate = $params['sale_date'] ?? null;

        if (!$lead || !($lead instanceof Lead)) {
            throw new Exception('BulkUploadLeadSaleDataDTO: invalid lead');
        }
        if (!$amount) {
            throw new Exception('BulkUploadLeadSaleDataDTO: missing amount');
        }
        if (!$saleDate) {
            throw new Exception('BulkUploadLeadSaleDataDTO: missing sale_date');
        }
    }

}
