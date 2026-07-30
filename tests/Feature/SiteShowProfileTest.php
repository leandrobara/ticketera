<?php

namespace Tests\Feature;

use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteShowProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_show_profile_without_redis_cache(): void
    {
        config(['app.enable_redis_cache' => false]);

        $show = Show::factory()->create([
            'title' => 'A public show',
            'slug' => 'a-public-show',
        ]);

        $this
            ->getJson("/api/site/show/{$show->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $show->id)
            ->assertJsonPath('data.title', 'A public show');
    }
}
