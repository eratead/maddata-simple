<?php

namespace App\Providers;

use App\Models\Audience;
use App\Policies\AudiencePolicy;
use Illuminate\Support\Facades\Gate;
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
        \App\Models\Campaign::observe(\App\Observers\CampaignObserver::class);

        Gate::policy(Audience::class, AudiencePolicy::class);
    }
}
