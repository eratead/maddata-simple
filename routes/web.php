<?php

use App\Http\Controllers\Agency\AgencyUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportApiController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::post('/ai/generate-locations', [App\Http\Controllers\AiLocationController::class, 'generate'])->middleware('throttle:10,1')->name('ai.locations');
    Route::post('/ai/campaign-assistant', [App\Http\Controllers\CampaignAssistantController::class, 'chat'])->middleware('throttle:10,1')->name('ai.campaign-assistant');
    Route::get('/', fn () => redirect('/dashboard'));
    Route::get('/dashboard', function () {
        $lastId = session('last_campaign_id');

        return $lastId ? redirect()->route('dashboard.campaign', $lastId) : redirect()->route('campaigns.index');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/campaigns/client/{client_id?}', [\App\Http\Controllers\CampaignController::class, 'index'])->name('campaigns_client.index');
    Route::resource('campaigns', \App\Http\Controllers\CampaignController::class);
    // upload
    Route::post('/campaigns/{campaign}/upload', [\App\Http\Controllers\CampaignController::class, 'upload'])
        ->name('campaigns.upload');

    // Audiences
    Route::get('/campaigns/{campaign}/audiences', [\App\Http\Controllers\CampaignController::class, 'audiencesJson'])->name('campaigns.audiences.json');
    Route::post('/campaigns/{campaign}/audiences/sync', [\App\Http\Controllers\CampaignController::class, 'syncAudiences'])->name('campaigns.audiences.sync');

    Route::get('/dashboard/{campaign}', [\App\Http\Controllers\DashboardController::class, 'show'])
        ->name('dashboard.campaign');

    // Creative Routes
    Route::controller(\App\Http\Controllers\CreativeController::class)->group(function () {
        Route::get('campaigns/{campaign}/creatives/create', 'create')->name('creatives.create');
        Route::post('campaigns/{campaign}/creatives', 'store')->name('creatives.store');
        Route::get('creatives/{creative}/edit', 'edit')->name('creatives.edit');
        Route::put('creatives/{creative}', 'update')->name('creatives.update');
        Route::delete('creatives/{creative}', 'destroy')->name('creatives.destroy');
        // File management
        Route::post('/creatives/{creative}/upload', 'upload')->name('creatives.upload');
        Route::delete('/creatives/files/{file}', 'deleteFile')->name('creatives.files.delete');
        Route::get('/creatives/files/{file}/preview', 'preview')->name('creatives.files.preview');
        Route::get('/creatives/files/{file}/download', 'downloadFile')->name('creatives.files.download');
        Route::get('/creatives/{creative}/download-all', 'downloadAll')->name('creatives.download-all');
    });

    Route::get('/dashboard/{campaign}/export', [DashboardController::class, 'exportExcel'])->name('dashboard.export.excel');

    // Agency Manager Routes — user and client management within their agency
    Route::prefix('agency/{agency}')
        ->middleware(['auth'])
        ->name('agency.')
        ->group(function () {
            Route::resource('users', AgencyUserController::class)->except(['show']);
            Route::resource('clients', \App\Http\Controllers\Agency\AgencyClientController::class)->except(['show']);
        });

    // Admin Routes
    Route::prefix('admin')->middleware(['admin'])->name('admin.')->group(function () {
        // Users
        Route::resource('users', \App\Http\Controllers\UserController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::post('/users/{user}/reset-2fa', [UserController::class, 'reset2fa'])
            ->name('users.reset-2fa');
        Route::get('/users/{user}/attach-client', [UserController::class, 'attachClient'])->name('users.attach-client');

        // Clients
        Route::resource('clients', \App\Http\Controllers\ClientController::class)->except(['show']);

        // Agencies
        Route::resource('agencies', \App\Http\Controllers\Admin\AgencyController::class)->except(['show']);
        // Audiences
        Route::get('/audiences', [\App\Http\Controllers\Admin\AudienceController::class, 'index'])->name('audiences.index');
        Route::post('/audiences', [\App\Http\Controllers\Admin\AudienceController::class, 'store'])->name('audiences.store');
        Route::put('/audiences/{audience}', [\App\Http\Controllers\Admin\AudienceController::class, 'update'])->name('audiences.update');
        Route::delete('/audiences/{audience}', [\App\Http\Controllers\Admin\AudienceController::class, 'destroy'])->name('audiences.destroy');
        Route::post('/audiences/upload', [\App\Http\Controllers\Admin\AudienceController::class, 'upload'])->name('audiences.upload');
        Route::post('roles/reorder', [\App\Http\Controllers\Admin\RoleController::class, 'reorder'])->name('roles.reorder');
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);

        // Activity Logs (admin only)
        Route::get('/activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

    // Campaign Changes CRM (admin OR can_see_logs)
    Route::prefix('admin')->middleware(['can_see_logs'])->name('admin.')->group(function () {
        Route::prefix('campaign-changes')->name('campaign_changes.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\CampaignChangeController::class, 'index'])->name('index');
            Route::get('/{campaign}', [App\Http\Controllers\Admin\CampaignChangeController::class, 'show'])->name('show');
            Route::post('/{campaign}/handle', [App\Http\Controllers\Admin\CampaignChangeController::class, 'markAsHandled'])->name('handle');
            Route::post('/{campaign}/download-all', [App\Http\Controllers\Admin\CampaignChangeController::class, 'downloadAll'])->name('download_all');
            Route::get('/log/{log}/download', [App\Http\Controllers\Admin\CampaignChangeController::class, 'download'])->name('download');
        });
    });
});

// / API
Route::prefix('api/reports')->middleware(['auth:sanctum', 'check-token-expiry', 'ability:reports:read'])->group(function () {
    Route::get('/summary/{campaign}', [ReportApiController::class, 'summary'])->name('reports.summary');
    Route::get('/by-date/{campaign}', [ReportApiController::class, 'byDate'])->name('reports.by-date');
    Route::get('/by-placement/{campaign}', [ReportApiController::class, 'byPlacement'])->name('reports.by-placement');
    Route::get('/campaigns', [\App\Http\Controllers\ReportApiController::class, 'campaigns'])->name('reports.campaigns');
});

// / API token
use App\Http\Controllers\TokenController;

Route::middleware(['auth', 'campaign_manager'])->group(function () {
    Route::get('/tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('/tokens', [TokenController::class, 'store'])->name('tokens.create');
    Route::delete('/tokens/{id}', [TokenController::class, 'destroy'])->name('tokens.destroy');
    Route::post('/tokens/{id}/extend', [TokenController::class, 'extend'])->name('tokens.extend');
});

require __DIR__.'/auth.php';
