<?php

use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Http\V1\Controllers\TeacherShiftsController;

// The teacher's own shifts (mobile app): see the schedule, propose changes, talk to the admins.
Route::middleware(['auth:sanctum', 'can:shifts.propose-own'])->prefix('teacher/shifts')->group(function () {
    Route::get('/', [TeacherShiftsController::class, 'show'])->name('teacher.shifts.show');
    Route::put('proposal', [TeacherShiftsController::class, 'save'])->name('teacher.shifts.save');
    Route::post('proposal/withdraw', [TeacherShiftsController::class, 'withdraw'])->name('teacher.shifts.withdraw');
    Route::post('proposal/accept', [TeacherShiftsController::class, 'accept'])->name('teacher.shifts.accept');
    Route::post('proposal/comments', [TeacherShiftsController::class, 'comment'])->name('teacher.shifts.comment');
});
