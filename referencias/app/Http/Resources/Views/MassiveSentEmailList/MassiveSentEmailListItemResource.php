<?php

namespace App\Http\Resources\Views\MassiveSentEmailList;

use Illuminate\Support\Facades\DB;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;


class MassiveSentEmailListItemResource extends JsonResource
{

    public function toArray($request)
    {
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields(['name', 'last_name']);

        $arr = [
            'user' => $userRs,
            'leads_count' => $this->resource->leads_count,
            'external_massive_id' => $this->resource->external_massive_id,
        ];

        $dto = $this->resource->getMailerMassiveDTO();
        $arr['mailerInfo']  = $dto ? $dto->toArray() : null;
        return $arr;
    }

}
