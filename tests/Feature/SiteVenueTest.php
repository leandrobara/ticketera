<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\Show;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_venue_for_a_published_season_without_redis_cache(): void
    {
        config(['app.enable_redis_cache' => false]);

        $show = Show::factory()->create();
        $venue = Venue::query()->create([
            'name' => 'Teatro Cacheado',
            'city' => 'Buenos Aires',
        ]);
        $season = Season::query()->create([
            'show_id' => $show->id,
            'venue_id' => $venue->id,
            'status' => 'published',
            'closed_season_id' => 0,
        ]);

        $this
            ->getJson("/api/site/season/{$season->id}/venue")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $venue->id)
            ->assertJsonPath('data.name', 'Teatro Cacheado');
    }
}
