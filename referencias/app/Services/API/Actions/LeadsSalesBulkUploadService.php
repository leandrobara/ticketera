<?php

namespace App\Services\API\Actions;

use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\Client;
use App\Services\API\LeadService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\LeadSaleService;
use Illuminate\Support\Facades\Validator;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Helpers\SpreadSheetLeadSaleImportHelper;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Exceptions\Services\LeadsBulkUploadService\ExceededRowsException;
use App\DTO\Import\SpreadSheetLeadSaleImport\SpreadSheetImportLeadSaleDTO;


class LeadsSalesBulkUploadService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadSaleService $leadSaleService,
        private readonly SpreadSheetLeadSaleImportHelper $spreadSheetLeadSaleImportHelper,
    ) {
    }


    public function getLeadSalesPreviewList(UploadedFile $file): Collection
    {
        $leadSaleDataDTOCollection = $this->spreadSheetLeadSaleImportHelper->parseFile($file);

        $isSuperUser = $this->loggedUserIsSuperUser();
        if ($isSuperUser && $leadSaleDataDTOCollection->count() > 1500) {
            throw new ExceededRowsException('exceeded_rows', 413);
        }
        if (!$isSuperUser && $leadSaleDataDTOCollection->count() > 500) {
            throw new ExceededRowsException('exceeded_rows', 413);
        }

        $leadIds = $leadSaleDataDTOCollection->pluck('leadId');
        $leads = $this->leadService->findByClientAndIds($this->getClient(), $leadIds);

        foreach ($leadSaleDataDTOCollection as $dto) {
            $leadDB = $leads->find($dto->leadId);
            $dto = $this->fillFormattedAmount($dto);
            $dto = $this->fillPreviewDTOLead($dto, $leadDB);
            $dto = $this->fillPreviewDTOAmount($dto);
            $dto = $this->fillPreviewDTODescription($dto);
            $dto = $this->fillPreviewDTODate($dto);
            $dto = $this->fillPreviewDTOPersistStatus($dto);
        }
        $dtoCollection = $leadSaleDataDTOCollection->sortBy('isPersistable');
        return $dtoCollection;
    }


    public function uploadLeadsSales(Collection $bulkUploadLeadsSalesDataDTO): Collection
    {
        $nonImportedLeadsSales = new Collection();
        $importedLeadsSales = new Collection();
        
        try {
            DB::beginTransaction();
            foreach ($bulkUploadLeadsSalesDataDTO as $bulkUploadLeadSaleDataDTO) {
                try {
                    $leadSaleAttrs = $bulkUploadLeadSaleDataDTO->getLeadSaleAttrs();
                    $lead = $leadSaleAttrs['lead'];
                    unset($leadSaleAttrs['lead']);

                    $leadSale = $this->leadSaleService->create($lead, $leadSaleAttrs);
                    $importedLeadsSales->push($leadSale);
                } catch (ExistentLeadException $e) {
                    $nonImportedLeadsSales->push($e->getLead());
                }
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return collect([
            'importedLeadsSales' => $importedLeadsSales, 'nonImportedLeadsSales' => $nonImportedLeadsSales
        ]);
    }


    private function fillPreviewDTOPersistStatus(SpreadSheetImportLeadSaleDTO $dto): SpreadSheetImportLeadSaleDTO
    {
        if ($dto->leadIdIsEmpty) {
            $dto->addNonPersistibleReason(SpreadSheetImportLeadSaleDTO::LEAD_ID_IS_EMPTY);
        }
        if (!$dto->leadWasFound) {
            $dto->addNonPersistibleReason(SpreadSheetImportLeadSaleDTO::NON_EXISTENT_LEAD);
        }
        if ($dto->amountIsEmpty) {
            $dto->addNonPersistibleReason(SpreadSheetImportLeadSaleDTO::AMOUNT_IS_EMPTY);
        } else if ($dto->amountFormatIsWrong) {
            $dto->addNonPersistibleReason(SpreadSheetImportLeadSaleDTO::AMOUNT_FORMAT_IS_WRONG);
        }
        if ($dto->saleDateIsEmpty) {
            $dto->addNonPersistibleReason(SpreadSheetImportLeadSaleDTO::SALE_DATE_IS_EMPTY);
        } else if ($dto->salesDateFormatIsWrong) {
            $dto->addNonPersistibleReason(SpreadSheetImportLeadSaleDTO::SALE_DATE_FORMAT_IS_WRONG);
        }

        if ($dto->descriptionIsTooLong) {
            $dto->addWarningReason(SpreadSheetImportLeadSaleDTO::DESCRIPTION_IS_TOO_LONG);
        }

        return $dto;
    }


    private function fillPreviewDTODate(SpreadSheetImportLeadSaleDTO $dto): SpreadSheetImportLeadSaleDTO
    {
        if ($dto->saleDate) {
            $dto->saleDateIsEmpty = false;

            $validator = Validator::make(
                ['date' => $dto->saleDate],
                ['date' => 'date_format:d/m/Y']
            );

            if (!$validator->fails()) {
                $dto->salesDateFormatIsWrong = false;
            }
        }
        
        return $dto;
    }


    private function fillPreviewDTODescription(SpreadSheetImportLeadSaleDTO $dto): SpreadSheetImportLeadSaleDTO
    {
        $MAX_DESCRIPTION_LENGTH = 1000;
        if (!$dto->description || ($dto->description && strlen($dto->description) < $MAX_DESCRIPTION_LENGTH)) {
            $dto->descriptionIsTooLong = false;
            return $dto;
        }

        return $dto;
    }


    private function fillPreviewDTOAmount(SpreadSheetImportLeadSaleDTO $dto): SpreadSheetImportLeadSaleDTO
    {
        if (!$dto->amount) {
            return $dto;
        }

        $amount = $dto->amount;
        $dto->amountIsEmpty = false;

        if (!is_numeric($amount) || $amount < 0) {
            return $dto;
        }

        $decimals = explode('.', $amount);
        if (count($decimals) > 1 && strlen($decimals[1]) > 2) {
            return $dto;
        }
        if (strpos($amount, ',') !== false) {
            return $dto;
        }
        if (substr_count($amount, '.') > 1) {
            return $dto;
        }

        $dto->amountFormatIsWrong = false;

        return $dto;
    }


    private function fillPreviewDTOLead(SpreadSheetImportLeadSaleDTO $dto, ?Lead $leadDB): SpreadSheetImportLeadSaleDTO
    {
        if ($dto->leadId) {
            $dto->leadIdIsEmpty = false;
        }

        if (!is_null($leadDB)) {
            $dto->leadWasFound = true;
        }

        return $dto;
    }


    private function fillFormattedAmount(SpreadSheetImportLeadSaleDTO $dto): SpreadSheetImportLeadSaleDTO
    {
        if (!$dto->amount) {
            return $dto;
        }

        $amount = $dto->amount;

        // Eliminate all commas and semicolons except the last one
        $amountWithoutCommas = preg_replace('/[.,](?=.*[.,])/', '', $amount);
        // Replace the last comma (if it exists) with a period
        $amountFormatted = preg_replace('/,([^,]*)$/', '.$1', $amountWithoutCommas);
        if (strpos($amountFormatted, '.') === false) {
            $dto->amount = $amountFormatted;
            return $dto;
        }
        list($integer, $decimal) = explode('.', $amountFormatted);
        $decimal = substr($decimal, 0, 2);
        $dto->amount = $integer . '.' . $decimal;
        return $dto;
    }

}
