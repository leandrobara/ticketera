<?php

namespace App\Repositories;

use App\Models\ShowImage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShowImageRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return ShowImage::query()
            ->with('show')
            ->when($filters['show_id'] ?? null, fn ($query, int $showId) => $query->where('show_id', $showId))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderBy('show_id')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->paginate($limit);
    }

    public function getOne(ShowImage $showImage): ShowImage
    {
        return $showImage->load('show');
    }

    public function unsetMainForShow(int $showId, ?int $ignoreShowImageId = null): void
    {
        ShowImage::query()
            ->where('show_id', $showId)
            ->where('is_main', true)
            ->when($ignoreShowImageId, fn ($query) => $query->where('id', '!=', $ignoreShowImageId))
            ->update(['is_main' => false]);
    }

    public function store(array $attrs): ShowImage
    {
        return ShowImage::create($attrs)->load('show');
    }

    public function update(ShowImage $showImage, array $attrs): ShowImage
    {
        $showImage->update($attrs);
        return $this->getOne($showImage->fresh());
    }

    public function delete(ShowImage $showImage): void
    {
        $showImage->delete();
    }
}
