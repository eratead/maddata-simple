<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        \App\Models\Creative::observe(\App\Observers\CreativeObserver::class);
        \App\Models\CreativeFile::observe(\App\Observers\CreativeFileObserver::class);
    }
}
