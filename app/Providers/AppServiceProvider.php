<?php

namespace App\Providers;

use App\Helpers\RedisHelper;
use App\Repositories\Site\ShowRepository;
use App\Repositories\Site\ShowRepositoryCache;
use App\Repositories\Site\SiteShowRepositoryInterface;
use App\Repositories\Site\SiteVenueRepositoryInterface;
use App\Repositories\Site\VenueRepository;
use App\Repositories\Site\VenueRepositoryCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SiteShowRepositoryInterface::class, function ($app): SiteShowRepositoryInterface {
            $repository = $app->make(ShowRepository::class);

            if (! config('app.enable_redis_cache')) {
                return $repository;
            }

            return new ShowRepositoryCache(
                $repository,
                $app->make(RedisHelper::class),
            );
        });

        $this->app->singleton(SiteVenueRepositoryInterface::class, function ($app): SiteVenueRepositoryInterface {
            $repository = $app->make(VenueRepository::class);

            if (! config('app.enable_redis_cache')) {
                return $repository;
            }

            return new VenueRepositoryCache(
                $repository,
                $app->make(RedisHelper::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }

        RateLimiter::for('admin-login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('comment-request', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes(10, 3)->by(hash('sha256', $email).'|'.$request->ip()),
                Limit::perHour(20)->by($request->ip()),
            ];
        });
    }
}
