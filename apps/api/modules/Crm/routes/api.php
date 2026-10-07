<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\V1\Controllers\TeacherTrialsController;

// The teacher app: my trial lessons and their assessments.
Route::middleware(['auth:sanctum', 'can:trials.assess'])->prefix('teacher/trials')->group(function () {
    Route::get('/', [TeacherTrialsController::class, 'index'])->name('teacher.trials');
    Route::put('{trial}/assessment', [TeacherTrialsController::class, 'assess'])->whereUuid('trial')->name('teacher.trials.assess');
});
