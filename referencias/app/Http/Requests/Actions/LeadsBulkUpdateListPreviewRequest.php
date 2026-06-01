<?php

namespace App\Http\Requests\Actions;

use App\Http\Requests\APIBaseRequest;

class LeadsBulkUpdateListPreviewRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'file' => ['required', 'file', 'mimes:xls,xlsx,csv'],
        ];
    }

}
