<?php

namespace App\Repositories\Site;

use App\Models\Show;

class ShowRepository implements SiteShowRepositoryInterface
{
    public function getPublicShow(Show $show): Show
    {
        return Show::query()
            ->with([
                'mainImage',
                'credits.person',
                'performanceHistories' => function ($query) {
                    $query
                        ->orderBy('sort_order')
                        ->orderByDesc('year')
                        ->orderBy('id');
                },
                'links' => function ($query) {
                    $query
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->findOrFail($show->id)
        ;
    }
}
