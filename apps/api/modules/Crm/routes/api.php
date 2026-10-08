<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\V1\Controllers\TeacherTrialsController;

// The teacher app: my trial lessons and their assessments (when the academy's plan includes the CRM).
Route::middleware(['auth:sanctum', 'plan.feature:crm', 'can:trials.assess'])->prefix('teacher/trials')->group(function () {
    Route::get('/', [TeacherTrialsController::class, 'index'])->name('teacher.trials');
    Route::put('{trial}/assessment', [TeacherTrialsController::class, 'assess'])->whereUuid('trial')->name('teacher.trials.assess');
});
