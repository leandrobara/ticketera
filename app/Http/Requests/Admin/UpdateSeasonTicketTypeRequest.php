<?php

namespace App\Http\Requests\Admin;

class UpdateSeasonTicketTypeRequest extends CreateSeasonTicketTypeRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), ['season_id' => 'sometimes|integer|exists:seasons,id', 'name' => 'sometimes', 'price' => 'sometimes']);
    }
}
