<?php

namespace App\Providers;

use App\Models\Audience;
use App\Policies\AudiencePolicy;
use App\Services\Health\HostFacts;
use App\Services\Health\Listeners\RecordFailedLogin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
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

        // Feeds health check X2. Registered explicitly rather than left to
        // Laravel's listener auto-discovery (which IS active): under discovery,
        // a renamed method silently unregisters this and X2 reports a confident
        // green "0 failed logins" forever. Also matches how the observers above
        // are wired — one convention, one place to look. The class lives under
        // app/Services/Health/Listeners, outside the discovery path, so it
        // cannot end up registered twice and double-count.
        Event::listen(Failed::class, RecordFailedLogin::class);
    }
}
