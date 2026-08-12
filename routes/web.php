<?php

use Illuminate\Support\Facades\Route;

// Explicit SPA entry routes for Cloudflare/browser refreshes.
Route::view('/', 'app')->name('spa.home');
Route::view('/portal', 'app')->name('spa.portal');
Route::view('/dashboard', 'app')->name('spa.dashboard');

// Nested client-side routes remain inside the React SPA.
Route::view('/portal/{any}', 'app')->where('any', '.*');
Route::view('/dashboard/{any}', 'app')->where('any', '.*');

// Final public SPA fallback. Never swallow API, health, or generated asset paths.
Route::view('/{any}', 'app')->where('any', '^(?!(?:api|up|build|storage)(?:/|$)).+');
