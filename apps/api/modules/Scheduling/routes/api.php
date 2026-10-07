<?php

use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Http\V1\Controllers\FamilyLessonsController;
use Modules\Scheduling\Http\V1\Controllers\TeacherLessonsController;
use Modules\Scheduling\Http\V1\Controllers\TeacherShiftsController;

// The teacher's own shifts (mobile app): see the schedule, propose changes, talk to the admins.
Route::middleware(['auth:sanctum', 'can:shifts.propose-own'])->prefix('teacher/shifts')->group(function () {
    Route::get('/', [TeacherShiftsController::class, 'show'])->name('teacher.shifts.show');
    Route::put('proposal', [TeacherShiftsController::class, 'save'])->name('teacher.shifts.save');
    Route::post('proposal/withdraw', [TeacherShiftsController::class, 'withdraw'])->name('teacher.shifts.withdraw');
    Route::post('proposal/accept', [TeacherShiftsController::class, 'accept'])->name('teacher.shifts.accept');
    Route::post('proposal/comments', [TeacherShiftsController::class, 'comment'])->name('teacher.shifts.comment');
});

// The teacher's lessons, and their time off.
Route::middleware('auth:sanctum')->prefix('teacher')->group(function () {
    Route::get('lessons', [TeacherLessonsController::class, 'lessons'])->name('teacher.lessons');
    Route::middleware('can:time-off.request-own')->group(function () {
        Route::get('time-off', [TeacherLessonsController::class, 'timeOff'])->name('teacher.time-off');
        Route::post('time-off', [TeacherLessonsController::class, 'requestTimeOff'])->middleware('idempotent')->name('teacher.time-off.request');
        Route::delete('time-off/{timeOff}', [TeacherLessonsController::class, 'withdrawTimeOff'])->whereUuid('timeOff')->name('teacher.time-off.withdraw');
    });
});

// Students and guardians: a learner's lessons, cancelling one under the policy, turning down a substitute.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('family/lessons', [FamilyLessonsController::class, 'index'])->name('family.lessons');
    Route::get('lessons/{session}/cancellation', [FamilyLessonsController::class, 'cancellation'])->name('lessons.cancellation');
    Route::post('lessons/{session}/cancel', [FamilyLessonsController::class, 'cancel'])->middleware('idempotent')->name('lessons.cancel');
    Route::post('lessons/{session}/refuse-substitute', [FamilyLessonsController::class, 'refuseSubstitute'])->middleware('idempotent')->name('lessons.refuse-substitute');
});
