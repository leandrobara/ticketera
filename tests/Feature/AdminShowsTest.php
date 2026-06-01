<?php

namespace Tests\Feature;

use App\Models\AdminAccessToken;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminShowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_create_a_show(): void
    {
        User::factory()->create([
            'email' => 'admin@ticketera.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@ticketera.test',
            'password' => 'password',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['token', 'user' => ['id', 'name', 'email', 'role']]])
            ->assertJsonPath('success', true);

        $token = $loginResponse->json('data.token');

        $this
            ->withToken($token)
            ->postJson('/api/admin/shows', [
                'title' => 'A Real Independent Play',
                'slug' => 'a-real-independent-play',
                'description' => 'A first show for the MVP.',
                'status' => 'published',
            ])
            ->assertCreated()
            ->assertJsonPath('title', 'A Real Independent Play')
            ->assertJsonPath('slug', 'a-real-independent-play')
            ->assertJsonPath('status', 'published');

        $this->assertDatabaseHas('shows', [
            'slug' => 'a-real-independent-play',
            'status' => 'published',
        ]);
    }

    public function test_authenticated_admin_can_list_shows_with_pagination_and_newest_first(): void
    {
        $token = $this->adminToken();

        $newestShow = null;

        foreach (range(1, 21) as $index) {
            $timestamp = now()->startOfSecond()->subMinutes(21 - $index);

            $show = Show::factory()->create([
                'title' => "Show {$index}",
                'slug' => "show-{$index}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            if ($index === 21) {
                $newestShow = $show;
            }
        }

        $this
            ->withToken($token)
            ->getJson('/api/admin/shows')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('data.0.id', $newestShow->id)
            ->assertJsonPath('data.0.title', 'Show 21');
    }

    public function test_authenticated_admin_can_search_shows_by_title_and_slug(): void
    {
        $token = $this->adminToken();

        Show::factory()->create([
            'title' => 'Sunrise Gala',
            'slug' => 'opening-night',
        ]);

        Show::factory()->create([
            'title' => 'Evening Encore',
            'slug' => 'sunrise-special',
        ]);

        Show::factory()->create([
            'title' => 'Unrelated Matinee',
            'slug' => 'matinee-special',
        ]);

        $this
            ->withToken($token)
            ->getJson('/api/admin/shows?search=sunrise')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Sunrise Gala'])
            ->assertJsonFragment(['slug' => 'sunrise-special'])
            ->assertJsonMissing(['title' => 'Unrelated Matinee'])
            ->assertJsonMissing(['slug' => 'matinee-special']);
    }

    public function test_authenticated_admin_can_show_a_show_via_route_model_binding(): void
    {
        $token = $this->adminToken();
        $show = Show::factory()->create([
            'title' => 'Binding Example',
            'slug' => 'binding-example',
            'description' => 'Route-bound show.',
            'main_image_path' => 'shows/binding-example.jpg',
            'status' => 'published',
        ]);

        $this
            ->withToken($token)
            ->getJson("/api/admin/shows/{$show->id}")
            ->assertOk()
            ->assertJsonPath('id', $show->id)
            ->assertJsonPath('title', 'Binding Example')
            ->assertJsonPath('slug', 'binding-example')
            ->assertJsonPath('description', 'Route-bound show.')
            ->assertJsonPath('main_image_path', 'shows/binding-example.jpg')
            ->assertJsonPath('status', 'published');
    }

    public function test_authenticated_admin_can_delete_a_show(): void
    {
        $token = $this->adminToken();
        $show = Show::factory()->create();

        $this
            ->withToken($token)
            ->deleteJson("/api/admin/shows/{$show->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('shows', [
            'id' => $show->id,
        ]);
    }

    public function test_admin_show_endpoints_require_authentication(): void
    {
        $show = Show::factory()->create();

        $this
            ->postJson('/api/admin/shows', [
                'title' => 'Unauthenticated Show',
                'slug' => 'unauthenticated-show',
                'description' => 'Should not be created.',
                'status' => 'draft',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 401)
            ->assertJsonPath('error.message', 'Unauthenticated.');

        $this
            ->getJson("/api/admin/shows/{$show->id}")
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 401)
            ->assertJsonPath('error.message', 'Unauthenticated.');

        $this
            ->putJson("/api/admin/shows/{$show->id}", [
                'title' => 'Unauthenticated Update',
                'slug' => 'unauthenticated-update',
                'description' => 'Should not update.',
                'status' => 'draft',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 401)
            ->assertJsonPath('error.message', 'Unauthenticated.');

        $this
            ->deleteJson("/api/admin/shows/{$show->id}")
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 401)
            ->assertJsonPath('error.message', 'Unauthenticated.');
    }

    public function test_admin_token_is_required_to_manage_shows(): void
    {
        $this
            ->getJson('/api/admin/shows')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 401)
            ->assertJsonPath('error.message', 'Unauthenticated.');
    }

    public function test_authenticated_admin_can_get_current_user(): void
    {
        $token = $this->adminToken();

        $this
            ->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data' => ['user' => ['id', 'name', 'email', 'role']]]);
    }

    public function test_authenticated_admin_can_logout(): void
    {
        $token = $this->adminToken();

        $this
            ->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Logged out.');

        $this->assertDatabaseMissing('admin_access_tokens', [
            'token_hash' => hash('sha256', $token),
        ]);
    }

    public function test_login_stores_only_the_hashed_admin_token(): void
    {
        $this->travelTo(now()->startOfSecond());

        User::factory()->create([
            'email' => 'admin@ticketera.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@ticketera.test',
            'password' => 'password',
        ]);

        $token = $response->json('data.token');

        $this->assertDatabaseHas('admin_access_tokens', [
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('admin_access_tokens', [
            'token_hash' => $token,
        ]);

        $accessToken = AdminAccessToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        $this->assertTrue(
            now()->addMinutes(config('auth.admin_access_tokens.ttl_minutes'))->equalTo($accessToken->expires_at)
        );
    }

    public function test_non_admin_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'user@ticketera.test',
            'password' => 'password',
            'role' => 'user',
        ]);

        $this
            ->postJson('/api/auth/login', [
                'email' => 'user@ticketera.test',
                'password' => 'password',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 401)
            ->assertJsonPath('error.message', 'invalid_user_or_password');
    }

    public function test_login_validation_errors_use_api_envelope(): void
    {
        $this
            ->post('/api/auth/login', ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 422)
            ->assertJsonPath('error.message', 'The email field is required.')
            ->assertJsonPath('error.fields.email.0', 'The email field is required.');
    }

    public function test_login_validation_returns_all_field_errors(): void
    {
        $this
            ->post('/api/auth/login')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 422)
            ->assertJsonPath('error.fields.email.0', 'The email field is required.')
            ->assertJsonPath('error.fields.password.0', 'The password field is required.');
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'admin@ticketera.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this
                ->postJson('/api/auth/login', [
                    'email' => 'admin@ticketera.test',
                    'password' => 'wrong-password',
                ])
                ->assertUnauthorized();
        }

        $this
            ->postJson('/api/auth/login', [
                'email' => 'admin@ticketera.test',
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 429)
            ->assertJsonPath('error.message', 'too_many_login_attempts');
    }

    public function test_expired_admin_token_is_rejected_and_deleted(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $token = Str::random(80);

        AdminAccessToken::create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
        ]);

        $this
            ->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 401)
            ->assertJsonPath('error.message', 'Unauthenticated.');

        $this->assertDatabaseMissing('admin_access_tokens', [
            'token_hash' => hash('sha256', $token),
        ]);
    }

    public function test_admin_can_update_a_show(): void
    {
        $show = Show::factory()->create(['status' => 'draft']);
        $token = $this->adminToken();

        $this
            ->withToken($token)
            ->putJson("/api/admin/shows/{$show->id}", [
                'title' => 'Updated Show',
                'slug' => 'updated-show',
                'description' => 'Updated description.',
                'status' => 'published',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Updated Show')
            ->assertJsonPath('status', 'published');

        $this->assertDatabaseHas('shows', [
            'id' => $show->id,
            'slug' => 'updated-show',
            'status' => 'published',
        ]);
    }

    private function adminToken(): string
    {
        $user = User::factory()->create(['role' => 'admin']);
        $token = Str::random(80);

        AdminAccessToken::create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(config('auth.admin_access_tokens.ttl_minutes')),
        ]);

        return $token;
    }
}
