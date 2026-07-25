<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CommentResourceCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'items' => $this->collection
                ->map(fn ($comment) => (new CommentResource($comment))->toArray($request))
                ->values()
                ->all(),
            'pagination' => [
                'page' => $this->currentPage(),
                'limit' => $this->perPage(),
                'total' => $this->total(),
                'last_page' => $this->lastPage(),
                'has_more' => $this->hasMorePages(),
            ],
        ];
    }
}
