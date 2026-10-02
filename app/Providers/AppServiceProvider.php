<?php

namespace App\Providers;

use App\Services\Fire\FireClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FireClient::class, fn () => FireClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Uploads and re-analysis call Fire (costs money); cap them per visitor.
        RateLimiter::for('fire', fn (Request $request) => Limit::perHour(20)->by($request->ip()));
    }
}
