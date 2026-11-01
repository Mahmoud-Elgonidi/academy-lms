<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\V1\Controllers\OwnTrialController;
use Modules\Crm\Http\V1\Controllers\TeacherTrialsController;

// The teacher app: my trial lessons and their assessments (when the academy's plan includes the CRM).
Route::middleware(['auth:sanctum', 'plan.feature:crm', 'can:trials.assess'])->prefix('teacher/trials')->group(function () {
    Route::get('/', [TeacherTrialsController::class, 'index'])->name('teacher.trials');
    Route::put('{trial}/assessment', [TeacherTrialsController::class, 'assess'])->whereUuid('trial')->name('teacher.trials.assess');
});

// A family books its own free trial from the academy's page, before anyone signs in (while the academy lets families book).
Route::get('site/trial-slots', [OwnTrialController::class, 'slots'])->middleware('throttle:60,1')->name('site.trial-slots');
Route::post('site/trials/code', [OwnTrialController::class, 'code'])->middleware('throttle:5,60')->name('site.trials.code');
Route::post('site/trials', [OwnTrialController::class, 'store'])->middleware(['throttle:10,60', 'idempotent'])->name('site.trials');
Route::get('site/trials/pick/{token}', [OwnTrialController::class, 'pick'])->where('token', '[A-Za-z0-9_\-.]{20,300}')->middleware('throttle:60,1')->name('site.trials.pick');
Route::post('site/trials/pick/{token}', [OwnTrialController::class, 'pickBook'])->where('token', '[A-Za-z0-9_\-.]{20,300}')->middleware(['throttle:10,60', 'idempotent'])->name('site.trials.pick.book');
