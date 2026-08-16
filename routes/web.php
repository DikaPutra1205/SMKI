<?php

use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — API Health & Info Endpoint (auth required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'app' => 'Sistem Kepatuhan Digital SMKI — Backend API (ISO 27001 & 27701)',
            'status' => 'online',
            'version' => '1.0.0',
            'environment' => config('app.env'),
            'api_prefix' => '/api',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::get('/welcome', function () {
        return Inertia::render('welcome');
    })->name('welcome');

    Route::prefix('admin/kepatuhan')->name('admin.kepatuhan.')->group(function () {
        Route::get('/', function () {
            return redirect()->route('admin.kepatuhan.dashboard');
        });

        Route::get('/dashboard', [ComplianceController::class, 'dashboard'])->name('dashboard');
        Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance');

        Route::post('/controls', [ComplianceController::class, 'store'])->name('controls.store');
        Route::patch('/controls/{control}', [ComplianceController::class, 'update'])->name('controls.update');
        Route::delete('/controls/{control}', [ComplianceController::class, 'destroy'])->name('controls.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Auth Routes (guest only) — temporary gate
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
