<?php

use Illuminate\Support\Facades\Route;

// The API lives under /api/v1 (registered by each module). Back-office panels come with Filament.
Route::get('/', fn () => ['app' => config('app.name'), 'api' => url('/api/v1')]);
