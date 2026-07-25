<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Season;

class ShowController extends Controller
{
    public function index(Season $season, ?string $slug = null)
    {
        abort_unless(
            in_array($season->status, ['published', 'finished'], true),
            404
        );

        $season->load('show');

        if ($slug !== $season->show->slug) {
            return redirect("/shows/{$season->id}/{$season->show->slug}");
        }

        return view('site.app', [
            'show' => $season->show,
            'season' => $season,
        ]);
    }
}
