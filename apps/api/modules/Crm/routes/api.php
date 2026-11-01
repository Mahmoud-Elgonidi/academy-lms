<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\V1\Controllers\FamilyTrialsController;
use Modules\Crm\Http\V1\Controllers\OwnTrialController;
use Modules\Crm\Http\V1\Controllers\TeacherTrialsController;
use Modules\Crm\Http\V1\Controllers\TrialReportController;

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

// After the trial (J2), before anyone signs in: the family's report, and the free second trial from it; "book again" after one that did not take place.
Route::prefix('site/trials')->where(['token' => '[A-Za-z0-9_\-.]{20,300}'])->group(function () {
    Route::get('report/{token}', [TrialReportController::class, 'report'])->middleware('throttle:60,1')->name('site.trials.report');
    Route::get('report/{token}/slots', [TrialReportController::class, 'reportSlots'])->middleware('throttle:60,1')->name('site.trials.report.slots');
    Route::post('report/{token}/book', [TrialReportController::class, 'reportBook'])->middleware(['throttle:10,60', 'idempotent'])->name('site.trials.report.book');
    Route::get('rebook/{token}', [TrialReportController::class, 'rebook'])->middleware('throttle:60,1')->name('site.trials.rebook');
    Route::get('rebook/{token}/slots', [TrialReportController::class, 'rebookSlots'])->middleware('throttle:60,1')->name('site.trials.rebook.slots');
    Route::post('rebook/{token}/book', [TrialReportController::class, 'rebookBook'])->middleware(['throttle:10,60', 'idempotent'])->name('site.trials.rebook.book');
});

// A signed-in family's free trials: the list, a report, and one more trial from it (whatever the academy's plan: nothing new is written but a booking).
Route::middleware('auth:sanctum')->prefix('family/trials')->group(function () {
    Route::get('/', [FamilyTrialsController::class, 'index'])->name('family.trials');
    Route::get('{session}', [FamilyTrialsController::class, 'show'])->whereUuid('session')->name('family.trials.show');
    Route::get('{session}/slots', [FamilyTrialsController::class, 'slots'])->whereUuid('session')->middleware('throttle:60,1')->name('family.trials.slots');
    Route::post('{session}/book', [FamilyTrialsController::class, 'book'])->whereUuid('session')->middleware(['throttle:10,60', 'idempotent'])->name('family.trials.book');
});
