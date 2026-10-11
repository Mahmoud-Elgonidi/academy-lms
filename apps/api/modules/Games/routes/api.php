<?php

use Illuminate\Support\Facades\Route;
use Modules\Games\Http\V1\Controllers\GamesController;

// Students and guardians: rounds on the journey's lessons, hatching, the album of creatures, a guardian's limits (and the old games world).
// Each write has its own tight limit on top of the api ceiling (a round is a minute or more of play).
Route::middleware('auth:sanctum')->prefix('games')->group(function () {
    Route::get('world', [GamesController::class, 'world'])->name('games.world');
    Route::post('plays', [GamesController::class, 'start'])->middleware('throttle:30,1')->name('games.plays.start');
    Route::post('plays/{play}/finish', [GamesController::class, 'finish'])->middleware(['idempotent', 'throttle:30,1'])->whereUuid('play')->name('games.plays.finish');
    Route::post('hatch', [GamesController::class, 'hatch'])->middleware(['idempotent', 'throttle:10,1'])->name('games.hatch');
    Route::put('limits', [GamesController::class, 'setLimits'])->middleware('throttle:20,1')->name('games.limits');
    Route::get('creatures', [GamesController::class, 'creatures'])->name('games.creatures');
    Route::put('creatures/{creature}', [GamesController::class, 'updateCreature'])->middleware('throttle:20,1')->where('creature', '[a-z0-9._-]{1,64}')->name('games.creatures.update');
});
