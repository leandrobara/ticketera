<?php

namespace App\Http\Requests\Views;

use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class MassiveEmailModalRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'lead_id' => ['required', 'array'],
            'lead_id.*' => ['required', 'integer'],
        ];
    }


    public function validatedLeadIds(): Collection
    {
        $validated = parent::validated();
        return collect($validated['lead_id']);
    }

}
