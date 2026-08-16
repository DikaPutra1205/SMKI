<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — API Health & Info Endpoint
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'app'         => 'Sistem Kepatuhan Digital SMKI — Backend API (ISO 27001 & 27701)',
        'status'      => 'online',
        'version'     => '1.0.0',
        'environment' => config('app.env'),
        'api_prefix'  => '/api',
        'timestamp'   => now()->toIso8601String(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Inertia Frontend Routes
|--------------------------------------------------------------------------
*/

Route::get('/welcome', function () {
    return Inertia::render('welcome');
})->name('welcome');
