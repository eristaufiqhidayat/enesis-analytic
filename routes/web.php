<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TargetController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// API memakai sesi web (cookie) sehingga SPA Vue cukup login sekali.
Route::prefix('api/v1')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::middleware('auth')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('dashboard/filters', [DashboardController::class, 'filters']);
        Route::get('dashboard/motoris', [DashboardController::class, 'motoris']);
        Route::get('config/targets', [DashboardController::class, 'targets']);
        Route::get('targets', [TargetController::class, 'index']);
        Route::post('targets', [TargetController::class, 'store']);
    });
});

// Semua halaman lain dilayani oleh SPA Vue (vue-router).
Route::view('/{any?}', 'app')->where('any', '^(?!api/).*$');
