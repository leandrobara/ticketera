<?php

namespace App\Http\Requests\Actions;

use DateTime;
use DateTimeZone;
use App\Models\Lead;
use App\Models\Client;
use App\Rules\IsArrayOfIntegers;
use App\Services\API\LeadService;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\DTO\Import\BulkUpload\BulkUploadLeadSaleDataDTO;


class LeadsSalesBulkUploadRequest extends APIBaseRequest
{

    private $leadDBList;

    public function rules()
    {
        return [
            'leadsSales' => ['required', 'array'],
            'leadsSales.*.lead_id' => ['required', 'integer'],
            'leadsSales.*.description' => ['sometimes', 'nullable', 'string'],
            'leadsSales.*.sale_date' =>  ['required', 'date_format:Y-m-d\TH:i:sP'],
            'leadsSales.*.amount' => ['required', 'numeric'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                $clientSettings = $client->clientSettings;
                
                if (!$clientSettings->enable_leads_sales_bulk_upload) {
                    $validator->errors()->add('leads_sales_bulk_upload', 'leads_sales_bulk_upload_is_not_enabled');
                    return false;
                }
                
                $leadsSalesCollection = collect(request()->input('leadsSales'));
                $leadIds = $leadsSalesCollection->pluck('lead_id')->filter()->unique();

                $this->leadDBList = resolve(LeadService::class)->findByClientAndIds($client, $leadIds);
                $this->leadDBList = $this->leadDBList->keyBy('id');

                if ($leadIds->count() != $this->leadDBList->count()) {
                    $validator->errors()->add('lead_id', 'some_leads_do_not_exist');
                    return false;
                }
            });
        }
    }


    public function validatedDTOs(): Collection
    {
        $dtoCollection = new Collection();
        $validated = parent::validated();

        foreach ($validated['leadsSales'] as $leadSaleParamsRow) {
            $leadDB = $this->getLeadById($leadSaleParamsRow['lead_id']);

            $saleDate = (new DateTime($leadSaleParamsRow['sale_date']))->setTimezone(new DateTimeZone('UTC'));
            // $saleDateStr = $saleDate->format('Y-m-d\TH:i:sP');
            $params = [
                'lead' => $leadDB,
                'sale_date' => $saleDate,
                'amount' => $leadSaleParamsRow['amount'],
                'description' => $leadSaleParamsRow['description'],
            ];

            $dto = BulkUploadLeadSaleDataDTO::build($params);
            $dtoCollection->push($dto);
        }

        return $dtoCollection;
    }


    protected function getLeadById(int $id): Lead
    {
        return $this->leadDBList->get($id);
    }

}
