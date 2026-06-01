<?php

namespace App\Http\Resources\Views\LeadList;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class LeadListPhonesResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $lead) {
            $leadContactPhonesArr = $lead->leadContactPhones->map(function ($leadContactPhone) {
                return [
                    'id' => $leadContactPhone->id,
                    'phone' => $leadContactPhone->phone,
                    'name' => $leadContactPhone->leadContact->name,
                    'company' => $leadContactPhone->leadContact->company,
                    'lastName' => $leadContactPhone->leadContact->last_name,
                ];
            });

            $response[] = [
                'id' => $lead->id,
                'company' => $lead->company,
                'leadContactPhones' => $leadContactPhonesArr
            ];
        }
        return $response;
    }

}
