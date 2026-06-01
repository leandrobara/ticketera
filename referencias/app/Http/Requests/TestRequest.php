<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Services\API\LeadService;
use App\Rules\InLeadReturnFields;
use App\DTO\CreateNewManualLeadDTO;


class TestRequest extends APIBaseRequest
{
    
    public function rules()
    {
        return [
        ];
    }


    public function withValidator($validator)
    {
        // $validator->after(function ($validator) {
        //     if (!$validator->failed()) {
        //         $validator->errors()->add('lead_create', 'lead_already_exists');
        //         return false;
        //     }
        // });
    }

}
