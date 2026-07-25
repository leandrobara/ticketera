<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PresentationResourceCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return $this->collection
            ->map(fn ($presentation) => (new PresentationResource($presentation))->toArray($request))
            ->values()
            ->all()
        ;
    }
}
