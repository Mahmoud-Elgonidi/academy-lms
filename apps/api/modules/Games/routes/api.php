<?php

use Illuminate\Support\Facades\Route;
use Modules\Games\Http\V1\Controllers\GamesController;

// Students and guardians: rounds on the journey's lessons, hatching, the album of creatures, a guardian's limits (and the old games world).
Route::middleware('auth:sanctum')->prefix('games')->group(function () {
    Route::get('world', [GamesController::class, 'world'])->name('games.world');
    Route::post('plays', [GamesController::class, 'start'])->name('games.plays.start');
    Route::post('plays/{play}/finish', [GamesController::class, 'finish'])->middleware('idempotent')->whereUuid('play')->name('games.plays.finish');
    Route::post('hatch', [GamesController::class, 'hatch'])->middleware('idempotent')->name('games.hatch');
    Route::put('limits', [GamesController::class, 'setLimits'])->name('games.limits');
    Route::get('creatures', [GamesController::class, 'creatures'])->name('games.creatures');
    Route::put('creatures/{creature}', [GamesController::class, 'updateCreature'])->where('creature', '[a-z0-9._-]{1,64}')->name('games.creatures.update');
});
