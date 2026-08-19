<?php

namespace App\Providers;

use App\Models\Audience;
use App\Policies\AudiencePolicy;
use App\Services\Health\HostFacts;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared so one snapshot build reads the host-facts file once, and so
        // SystemHealthService::build()'s flush() actually invalidates the copy
        // every check class is holding.
        $this->app->singleton(HostFacts::class);
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
