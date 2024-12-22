<?php

namespace App\Providers;

use App\Labeler\ArtisanLabeler;
use Illuminate\Support\ServiceProvider;
use Revolution\Bluesky\Labeler\Labeler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Labeler::register(ArtisanLabeler::class);
    }
}
