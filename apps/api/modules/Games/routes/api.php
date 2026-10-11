<?php

use Illuminate\Support\Facades\Route;
use Modules\Games\Http\V1\Controllers\GamesController;

// Students and guardians: the games world, rounds, and a guardian's limits.
Route::middleware('auth:sanctum')->prefix('games')->group(function () {
    Route::get('world', [GamesController::class, 'world'])->name('games.world');
    Route::post('plays', [GamesController::class, 'start'])->name('games.plays.start');
    Route::post('plays/{play}/finish', [GamesController::class, 'finish'])->middleware('idempotent')->whereUuid('play')->name('games.plays.finish');
    Route::put('limits', [GamesController::class, 'setLimits'])->name('games.limits');
});
