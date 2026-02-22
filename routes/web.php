<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportApiController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


Route::middleware(['auth'])->group(function () {
    Route::get('/', fn() => redirect('/dashboard'));
    Route::get('/dashboard', function () {
        $lastId = session('last_campaign_id');
        return $lastId ? redirect()->route('dashboard.campaign', $lastId) : redirect()->route('campaigns.index');
    })->middleware('auth')->name('dashboard');

    // Future routes: campaigns, clients, etc.
    Route::resource('users', \App\Http\Controllers\UserController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('auth');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');



    Route::resource('clients', \App\Http\Controllers\ClientController::class);

    Route::get('/campaigns/client/{client_id?}', [\App\Http\Controllers\CampaignController::class, 'index'])->name('campaigns_client.index');
    Route::resource('campaigns', \App\Http\Controllers\CampaignController::class)
        ->middleware('auth');
    //upload
    Route::post('/campaigns/{campaign}/upload', [\App\Http\Controllers\CampaignController::class, 'upload'])
        ->middleware('auth')
        ->name('campaigns.upload');

    Route::get('/dashboard/{campaign}', [\App\Http\Controllers\DashboardController::class, 'show'])
        ->middleware('auth')
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
    })->middleware('auth');

    Route::get('/dashboard/{campaign}/export', [DashboardController::class, 'exportExcel'])->name('dashboard.export.excel');

    Route::get('/users/{user}/attach-client', [UserController::class, 'attachClient'])->name('users.attach-client');

    // Admin Routes
    Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {
        Route::get('/activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');

        // Campaign Changes CRM
        Route::prefix('campaign-changes')->name('campaign_changes.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\CampaignChangeController::class, 'index'])->name('index');
            Route::get('/{campaign}', [App\Http\Controllers\Admin\CampaignChangeController::class, 'show'])->name('show');
            Route::post('/{campaign}/handle', [App\Http\Controllers\Admin\CampaignChangeController::class, 'markAsHandled'])->name('handle');
            Route::post('/{campaign}/download-all', [App\Http\Controllers\Admin\CampaignChangeController::class, 'downloadAll'])->name('download_all');
            Route::get('/log/{log}/download', [App\Http\Controllers\Admin\CampaignChangeController::class, 'download'])->name('download');
        });
    });
});

/// API
Route::prefix('api/reports')->middleware(['auth:sanctum', 'check-token-expiry'])->group(function () {
    Route::get('/summary/{campaign}', [ReportApiController::class, 'summary'])->name('reports.summary');
    Route::get('/by-date/{campaign}', [ReportApiController::class, 'byDate']);
    Route::get('/by-placement/{campaign}', [ReportApiController::class, 'byPlacement']);
    Route::get('/campaigns', [\App\Http\Controllers\ReportApiController::class, 'campaigns']);
});


/// API token
use App\Http\Controllers\TokenController;

Route::middleware('auth')->group(function () {
    Route::get('/tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('/tokens', [TokenController::class, 'store'])->name('tokens.create');
    Route::delete('/tokens/{id}', [TokenController::class, 'destroy'])->name('tokens.destroy');
    Route::post('/tokens/{id}/extend', [TokenController::class, 'extend'])->name('tokens.extend');
});


require __DIR__ . '/auth.php';
