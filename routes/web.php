<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

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

    Route::get('/dashboard/{campaign}/export', [DashboardController::class, 'exportExcel'])->name('dashboard.export.excel');
});

// test

require __DIR__ . '/auth.php';
