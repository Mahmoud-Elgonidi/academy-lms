<?php

use Illuminate\Support\Facades\Route;
use Modules\Evaluation\Http\Controllers\RateLessonController;

// Rating a lesson on the academy's own host, without signing in: each person's own signed link from the message.
Route::middleware(['web', 'tenant'])->prefix('rate')->group(function () {
    Route::get('{session}', [RateLessonController::class, 'show'])->middleware('signed:relative')->name('evaluation.rate');
    Route::post('{session}', [RateLessonController::class, 'store'])->middleware(['signed:relative', 'throttle:20,1'])->name('evaluation.rate.store');
});
