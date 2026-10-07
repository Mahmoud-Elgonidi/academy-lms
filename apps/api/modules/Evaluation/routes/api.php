<?php

use Illuminate\Support\Facades\Route;
use Modules\Evaluation\Http\V1\Controllers\RatingsController;

Route::middleware('auth:sanctum')->group(function () {
    // The family app: one-tap rating of a lesson that took place.
    Route::get('lessons/{session}/rating', [RatingsController::class, 'show'])->name('lessons.rating');
    Route::put('lessons/{session}/rating', [RatingsController::class, 'save'])->name('lessons.rating.save');
    // The teacher app: my own numbers (never shown to families).
    Route::get('teacher/performance', [RatingsController::class, 'performance'])->middleware('can:performance.view-own')->name('teacher.performance');
});
