<?php

use Illuminate\Support\Facades\Route;
use Modules\People\Http\V1\Controllers\ConsentController;
use Modules\People\Http\V1\Controllers\FamilyController;
use Modules\People\Http\V1\Controllers\TeacherChecksController;

// The family app: whose account the person signed in can open (themselves as a student, their children).
Route::middleware('auth:sanctum')->get('family/learners', [FamilyController::class, 'learners'])->name('family.learners');

// A guardian's consent to their children's accounts.
Route::middleware('auth:sanctum')->prefix('family/consents')->group(function () {
    Route::get('/', [ConsentController::class, 'index'])->name('family.consents');
    Route::post('{student}', [ConsentController::class, 'grant'])->middleware('throttle:20,1')->name('family.consents.grant');
    Route::post('{student}/withdraw', [ConsentController::class, 'withdraw'])->middleware('throttle:20,1')->name('family.consents.withdraw');
});

// A teacher's own vetting documents (the same as "My documents" in /teach).
Route::middleware('auth:sanctum')->prefix('teacher/checks')->group(function () {
    Route::get('/', [TeacherChecksController::class, 'index'])->name('teacher.checks');
    Route::post('/', [TeacherChecksController::class, 'store'])->middleware(['idempotent', 'throttle:10,1'])->name('teacher.checks.store');
    Route::get('{check}/file', [TeacherChecksController::class, 'file'])->name('teacher.checks.file');
});
